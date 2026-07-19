import { Form, Head, Link, useForm } from '@inertiajs/react';
import {
    CategorySelect,
    type CatalogTaxonomy,
} from '@/components/catalog-taxonomy-picker';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as productsIndex,
    update,
} from '@/routes/products';

type Option = { id: number; name: string };

type ProductDetail = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    description: string | null;
    category_id: number | null;
    category_name: string | null;
    cost_price: string;
    selling_price: string;
    cost_price_formatted: string;
    selling_price_formatted: string;
    reorder_level: number;
    is_active: boolean;
    supplier_ids: number[];
    suppliers: Option[];
    stock_by_branch: Array<{
        id: number;
        branch_id: number;
        branch_name: string | null;
        quantity: number;
        is_low_stock: boolean;
        value_formatted: string;
    }>;
    movements: Array<{
        id: number;
        type: string;
        quantity_delta: number;
        quantity_before: number;
        quantity_after: number;
        reason: string | null;
        note: string | null;
        branch_name: string | null;
        user_name: string | null;
        created_at: string | null;
    }>;
};

export default function ProductShow({
    product,
    taxonomy,
    suppliers,
    currency,
}: {
    product: ProductDetail;
    taxonomy: CatalogTaxonomy;
    suppliers: Option[];
    currency: string;
}) {
    const form = useForm({
        name: product.name,
        sku: product.sku,
        barcode: product.barcode ?? '',
        category_id: product.category_id ?? '',
        description: product.description ?? '',
        cost_price: product.cost_price,
        selling_price: product.selling_price,
        reorder_level: String(product.reorder_level),
        is_active: product.is_active,
        supplier_ids: product.supplier_ids,
    });

    const toggleSupplier = (id: number) => {
        const current = form.data.supplier_ids;
        form.setData(
            'supplier_ids',
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    return (
        <>
            <Head title={product.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {product.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {product.sku}
                            {product.category_name
                                ? ` · ${product.category_name}`
                                : ''}{' '}
                            · {product.selling_price_formatted}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={productsIndex()}>Back to products</Link>
                    </Button>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Product details</h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.patch(update.url(product.id), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="sku">SKU</Label>
                                    <Input
                                        id="sku"
                                        value={form.data.sku}
                                        onChange={(e) =>
                                            form.setData('sku', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.sku} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="barcode">Barcode</Label>
                                    <Input
                                        id="barcode"
                                        value={form.data.barcode}
                                        onChange={(e) =>
                                            form.setData(
                                                'barcode',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <CategorySelect
                                        taxonomy={taxonomy}
                                        value={form.data.category_id}
                                        onChange={(value) =>
                                            form.setData('category_id', value)
                                        }
                                        idPrefix="edit-product"
                                    />
                                    <InputError
                                        message={form.errors.category_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="cost_price">
                                        Cost ({currency})
                                    </Label>
                                    <Input
                                        id="cost_price"
                                        value={form.data.cost_price}
                                        onChange={(e) =>
                                            form.setData(
                                                'cost_price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.cost_price}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="selling_price">
                                        Selling ({currency})
                                    </Label>
                                    <Input
                                        id="selling_price"
                                        value={form.data.selling_price}
                                        onChange={(e) =>
                                            form.setData(
                                                'selling_price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.selling_price}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reorder_level">
                                        Reorder level
                                    </Label>
                                    <Input
                                        id="reorder_level"
                                        type="number"
                                        min={0}
                                        value={form.data.reorder_level}
                                        onChange={(e) =>
                                            form.setData(
                                                'reorder_level',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Active
                                </label>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Suppliers</Label>
                                <div className="max-h-40 space-y-2 overflow-y-auto rounded-md border border-border/70 p-2">
                                    {suppliers.map((supplier) => (
                                        <label
                                            key={supplier.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.supplier_ids.includes(
                                                    supplier.id,
                                                )}
                                                onChange={() =>
                                                    toggleSupplier(supplier.id)
                                                }
                                            />
                                            {supplier.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save changes
                                </Button>
                                <Form {...destroy.form(product.id)}>
                                    {() => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                        >
                                            Delete product
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </form>
                    </section>

                    <div className="space-y-6">
                        <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <h2 className="mb-3 font-medium">
                                Stock by branch
                            </h2>
                            {product.stock_by_branch.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No stock recorded at any accessible branch.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/70">
                                    {product.stock_by_branch.map((row) => (
                                        <li
                                            key={row.id}
                                            className="flex items-center justify-between py-3"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {row.branch_name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    Value {row.value_formatted}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">
                                                    {row.quantity}
                                                </p>
                                                {row.is_low_stock && (
                                                    <Badge variant="secondary">
                                                        Low stock
                                                    </Badge>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <h2 className="mb-3 font-medium">
                                Movement history
                            </h2>
                            {product.movements.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No movements yet.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/70">
                                    {product.movements.map((movement) => (
                                        <li key={movement.id} className="py-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="font-medium capitalize">
                                                    {movement.type.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </p>
                                                <p
                                                    className={
                                                        movement.quantity_delta <
                                                        0
                                                            ? 'text-destructive'
                                                            : 'text-foreground'
                                                    }
                                                >
                                                    {movement.quantity_delta >
                                                    0
                                                        ? '+'
                                                        : ''}
                                                    {movement.quantity_delta}
                                                </p>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {movement.branch_name} ·{' '}
                                                {movement.quantity_before} →{' '}
                                                {movement.quantity_after}
                                                {movement.reason
                                                    ? ` · ${movement.reason}`
                                                    : ''}
                                                {movement.note
                                                    ? ` · ${movement.note}`
                                                    : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

ProductShow.layout = {
    breadcrumbs: [{ title: 'Products', href: productsIndex() }],
};
