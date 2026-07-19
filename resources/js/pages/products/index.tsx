import { Head, Link, router, useForm } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    CategorySelect,
    type CatalogTaxonomy,
} from '@/components/catalog-taxonomy-picker';
import InputError from '@/components/input-error';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as categoriesIndex } from '@/routes/categories';
import {
    index as productsIndex,
    show as productsShow,
    store,
} from '@/routes/products';

type ProductRow = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    category_id: number | null;
    category_name: string | null;
    cost_price: string;
    selling_price: string;
    cost_price_formatted: string;
    selling_price_formatted: string;
    reorder_level: number;
    is_active: boolean;
    suppliers_count: number;
};

type Option = { id: number; name: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function ProductsIndex({
    products,
    taxonomy,
    suppliers,
    filters,
    currency,
}: {
    products: Paginated<ProductRow>;
    taxonomy: CatalogTaxonomy;
    suppliers: Option[];
    filters: {
        search: string;
        category_id: number | null;
        status: string | null;
    };
    currency: string;
}) {
    const [search, setSearch] = useState(filters.search);
    const [categoryId, setCategoryId] = useState(
        filters.category_id ? String(filters.category_id) : '',
    );
    const [status, setStatus] = useState(filters.status ?? '');

    const createForm = useForm({
        name: '',
        sku: '',
        barcode: '',
        category_id: '' as string | number,
        description: '',
        cost_price: '',
        selling_price: '',
        reorder_level: '0',
        is_active: true,
        supplier_ids: [] as number[],
    });

    const toggleSupplier = (id: number) => {
        const current = createForm.data.supplier_ids;
        createForm.setData(
            'supplier_ids',
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    useEffect(() => {
        const timer = window.setTimeout(() => {
            const nextCategoryId = categoryId || undefined;
            const nextStatus = status || undefined;

            if (
                search === filters.search &&
                (filters.category_id ?? null) ===
                    (nextCategoryId ? Number(nextCategoryId) : null) &&
                (filters.status ?? null) === (nextStatus ?? null)
            ) {
                return;
            }

            router.get(
                productsIndex.url({
                    query: {
                        search,
                        category_id: nextCategoryId,
                        status: nextStatus,
                    },
                }),
                {},
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => window.clearTimeout(timer);
    }, [search, categoryId, status, filters]);

    return (
        <>
            <Head title="Products" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Products
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Catalog priced in {currency}. Assign products to a
                            category.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={categoriesIndex()}>Categories</Link>
                        </Button>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                placeholder="Search name, SKU, barcode"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                className="w-44 sm:w-56"
                            />
                            <select
                                value={categoryId}
                                onChange={(event) =>
                                    setCategoryId(event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All categories</option>
                                {taxonomy.categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={status}
                                onChange={(event) =>
                                    setStatus(event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_24rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {products.data.length === 0 ? (
                            <EmptyState
                                title="No products yet"
                                description="Create your first product to start tracking inventory."
                                icon={<Package className="size-5" />}
                            />
                        ) : (
                            <>
                                <ul className="divide-y divide-border/70">
                                    {products.data.map((product) => (
                                        <li
                                            key={product.id}
                                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Link
                                                        href={productsShow(
                                                            product.id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {product.name}
                                                    </Link>
                                                    <Badge
                                                        variant={
                                                            product.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {product.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {product.sku}
                                                    {product.category_name
                                                        ? ` · ${product.category_name}`
                                                        : ''}{' '}
                                                    · Cost{' '}
                                                    {
                                                        product.cost_price_formatted
                                                    }{' '}
                                                    · Sell{' '}
                                                    {
                                                        product.selling_price_formatted
                                                    }
                                                </p>
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={productsShow(
                                                        product.id,
                                                    )}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                                <Pagination links={products.links} />
                            </>
                        )}
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Add product</h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                createForm.post(store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        createForm.reset(
                                            'name',
                                            'sku',
                                            'barcode',
                                            'description',
                                            'cost_price',
                                            'selling_price',
                                            'category_id',
                                        );
                                    },
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={createForm.data.name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={createForm.errors.name} />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="sku">SKU</Label>
                                    <Input
                                        id="sku"
                                        value={createForm.data.sku}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'sku',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.sku}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="barcode">Barcode</Label>
                                    <Input
                                        id="barcode"
                                        value={createForm.data.barcode}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'barcode',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <CategorySelect
                                taxonomy={taxonomy}
                                value={createForm.data.category_id}
                                onChange={(value) =>
                                    createForm.setData('category_id', value)
                                }
                                idPrefix="create-product"
                            />
                            <InputError
                                message={createForm.errors.category_id}
                            />
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="cost_price">
                                        Cost ({currency})
                                    </Label>
                                    <Input
                                        id="cost_price"
                                        inputMode="decimal"
                                        value={createForm.data.cost_price}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'cost_price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.cost_price}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="selling_price">
                                        Selling ({currency})
                                    </Label>
                                    <Input
                                        id="selling_price"
                                        inputMode="decimal"
                                        value={createForm.data.selling_price}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'selling_price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            createForm.errors.selling_price
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reorder_level">
                                    Reorder level
                                </Label>
                                <Input
                                    id="reorder_level"
                                    type="number"
                                    min={0}
                                    value={createForm.data.reorder_level}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'reorder_level',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            {suppliers.length > 0 && (
                                <div className="grid gap-2">
                                    <Label>Suppliers</Label>
                                    <div className="max-h-36 space-y-2 overflow-y-auto rounded-md border border-border/70 p-2">
                                        {suppliers.map((supplier) => (
                                            <label
                                                key={supplier.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={createForm.data.supplier_ids.includes(
                                                        supplier.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleSupplier(
                                                            supplier.id,
                                                        )
                                                    }
                                                />
                                                {supplier.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="w-full"
                            >
                                Create product
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: productsIndex() }],
};
