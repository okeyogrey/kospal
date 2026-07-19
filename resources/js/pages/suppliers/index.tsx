import { Form, Head, router, useForm } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as suppliersIndex,
    store,
    update,
} from '@/routes/suppliers';

type ProductOption = {
    id: number;
    name: string;
    sku: string;
};

type SupplierRow = {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    products_count: number;
    product_ids: number[];
    products: ProductOption[];
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

function ProductMultiSelect({
    id,
    products,
    selectedIds,
    onChange,
    error,
}: {
    id: string;
    products: ProductOption[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return products;
        }

        return products.filter((product) =>
            [product.name, product.sku].join(' ').toLowerCase().includes(term),
        );
    }, [products, query]);

    const toggleProduct = (productId: number) => {
        onChange(
            selectedIds.includes(productId)
                ? selectedIds.filter((id) => id !== productId)
                : [...selectedIds, productId],
        );
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Products</Label>
            <Input
                id={id}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Filter products by name or SKU"
            />
            <div className="max-h-40 space-y-2 overflow-y-auto rounded-md border border-border/70 p-2">
                {filtered.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No products match your search.
                    </p>
                ) : (
                    filtered.map((product) => (
                        <label
                            key={product.id}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                checked={selectedIds.includes(product.id)}
                                onChange={() => toggleProduct(product.id)}
                            />
                            {product.name} ({product.sku})
                        </label>
                    ))
                )}
            </div>
            <InputError message={error} />
        </div>
    );
}

export default function SuppliersIndex({
    suppliers,
    products,
    filters,
}: {
    suppliers: Paginated<SupplierRow>;
    products: ProductOption[];
    filters: { search: string };
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const createForm = useForm({
        name: '',
        contact_name: '',
        email: '',
        phone: '',
        address: '',
        notes: '',
        is_active: true,
        product_ids: [] as number[],
    });
    const editForm = useForm({
        name: '',
        contact_name: '',
        email: '',
        phone: '',
        address: '',
        notes: '',
        is_active: true,
        product_ids: [] as number[],
    });

    const startEdit = (supplier: SupplierRow) => {
        setEditingId(supplier.id);
        editForm.setData({
            name: supplier.name,
            contact_name: supplier.contact_name ?? '',
            email: supplier.email ?? '',
            phone: supplier.phone ?? '',
            address: supplier.address ?? '',
            notes: supplier.notes ?? '',
            is_active: supplier.is_active,
            product_ids: supplier.product_ids,
        });
        editForm.clearErrors();
    };

    return (
        <>
            <Head title="Suppliers" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Suppliers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage supplier contacts and product relationships.
                        </p>
                    </div>
                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = new FormData(event.currentTarget);
                            router.get(
                                suppliersIndex.url({
                                    query: {
                                        search: String(
                                            data.get('search') ?? '',
                                        ),
                                    },
                                }),
                                {},
                                { preserveState: true },
                            );
                        }}
                    >
                        <Input
                            name="search"
                            placeholder="Search suppliers"
                            defaultValue={filters.search}
                            className="w-48 sm:w-64"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {suppliers.data.length === 0 ? (
                            <EmptyState
                                title="No suppliers yet"
                                description="Add suppliers to link them to products."
                                icon={<Truck className="size-5" />}
                            />
                        ) : (
                            <>
                                <ul className="divide-y divide-border/70">
                                    {suppliers.data.map((supplier) => (
                                        <li key={supplier.id} className="p-4">
                                            {editingId === supplier.id ? (
                                                <form
                                                    className="grid gap-3"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        editForm.patch(
                                                            update.url(
                                                                supplier.id,
                                                            ),
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                                onSuccess: () =>
                                                                    setEditingId(
                                                                        null,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <Input
                                                            value={
                                                                editForm.data
                                                                    .name
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Name"
                                                            required
                                                        />
                                                        <Input
                                                            value={
                                                                editForm.data
                                                                    .contact_name
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'contact_name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Contact"
                                                        />
                                                        <Input
                                                            value={
                                                                editForm.data
                                                                    .email
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'email',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Email"
                                                        />
                                                        <Input
                                                            value={
                                                                editForm.data
                                                                    .phone
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'phone',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Phone"
                                                        />
                                                    </div>
                                                    <ProductMultiSelect
                                                        id={`edit-products-${supplier.id}`}
                                                        products={products}
                                                        selectedIds={
                                                            editForm.data
                                                                .product_ids
                                                        }
                                                        onChange={(ids) =>
                                                            editForm.setData(
                                                                'product_ids',
                                                                ids,
                                                            )
                                                        }
                                                        error={
                                                            editForm.errors
                                                                .product_ids
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            editForm.errors.name
                                                        }
                                                    />
                                                    <div className="flex gap-2">
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            disabled={
                                                                editForm.processing
                                                            }
                                                        >
                                                            Save
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setEditingId(
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                </form>
                                            ) : (
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <p className="font-medium">
                                                                {supplier.name}
                                                            </p>
                                                            <Badge
                                                                variant={
                                                                    supplier.is_active
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {supplier.is_active
                                                                    ? 'Active'
                                                                    : 'Inactive'}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {[
                                                                supplier.contact_name,
                                                                supplier.phone,
                                                                supplier.email,
                                                            ]
                                                                .filter(
                                                                    Boolean,
                                                                )
                                                                .join(' · ') ||
                                                                'No contact details'}{' '}
                                                            ·{' '}
                                                            {
                                                                supplier.products_count
                                                            }{' '}
                                                            products
                                                        </p>
                                                        {supplier.products
                                                            .length > 0 ? (
                                                            <p className="mt-1 text-sm text-muted-foreground">
                                                                Linked:{' '}
                                                                {supplier.products
                                                                    .map(
                                                                        (
                                                                            product,
                                                                        ) =>
                                                                            product.name,
                                                                    )
                                                                    .join(', ')}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                startEdit(
                                                                    supplier,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </Button>
                                                        <Form
                                                            {...update.form(
                                                                supplier.id,
                                                            )}
                                                            options={{
                                                                preserveScroll:
                                                                    true,
                                                            }}
                                                        >
                                                            {() => (
                                                                <>
                                                                    <input
                                                                        type="hidden"
                                                                        name="name"
                                                                        value={
                                                                            supplier.name
                                                                        }
                                                                    />
                                                                    <input
                                                                        type="hidden"
                                                                        name="is_active"
                                                                        value={
                                                                            supplier.is_active
                                                                                ? 0
                                                                                : 1
                                                                        }
                                                                    />
                                                                    <Button
                                                                        type="submit"
                                                                        size="sm"
                                                                        variant="outline"
                                                                    >
                                                                        {supplier.is_active
                                                                            ? 'Deactivate'
                                                                            : 'Activate'}
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </Form>
                                                        <Form
                                                            {...destroy.form(
                                                                supplier.id,
                                                            )}
                                                            options={{
                                                                preserveScroll:
                                                                    true,
                                                            }}
                                                        >
                                                            {() => (
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                >
                                                                    Delete
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    </div>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                                <Pagination links={suppliers.links} />
                            </>
                        )}
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Add supplier</h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                createForm.post(store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () => createForm.reset(),
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
                            <div className="grid gap-2">
                                <Label htmlFor="contact_name">Contact</Label>
                                <Input
                                    id="contact_name"
                                    value={createForm.data.contact_name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'contact_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    value={createForm.data.phone}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'phone',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <ProductMultiSelect
                                id="create-products"
                                products={products}
                                selectedIds={createForm.data.product_ids}
                                onChange={(ids) =>
                                    createForm.setData('product_ids', ids)
                                }
                                error={createForm.errors.product_ids}
                            />
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="w-full"
                            >
                                Create supplier
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: suppliersIndex() }],
};
