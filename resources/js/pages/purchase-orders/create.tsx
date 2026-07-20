import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { id: number; name: string };

type ProductOption = {
    id: number;
    name: string;
    sku?: string | null;
    cost_price?: number | null;
};

type LineItem = {
    product_id: string;
    quantity_ordered: string;
    unit_cost: string;
};

export default function PurchaseOrdersCreate({
    suppliers,
    branches,
    products,
    defaultBranchId,
    currency,
}: {
    suppliers: Option[];
    branches: Option[];
    products: ProductOption[];
    defaultBranchId: number | null;
    currency: string;
}) {
    const form = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        branch_id: String(defaultBranchId ?? branches[0]?.id ?? ''),
        expected_at: '',
        notes: '',
        items: [
            { product_id: '', quantity_ordered: '1', unit_cost: '0' },
        ] as LineItem[],
    });

    const productCost = (productId: string): string => {
        const product = products.find((p) => String(p.id) === productId);

        return product?.cost_price != null ? String(product.cost_price) : '0';
    };

    const addLine = () => {
        form.setData('items', [
            ...form.data.items,
            { product_id: '', quantity_ordered: '1', unit_cost: '0' },
        ]);
    };

    const removeLine = (index: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };

    const updateLine = (
        index: number,
        field: keyof LineItem,
        value: string,
    ) => {
        form.setData(
            'items',
            form.data.items.map((item, i) => {
                if (i !== index) {
                    return item;
                }

                if (field === 'product_id') {
                    return {
                        ...item,
                        product_id: value,
                        unit_cost: productCost(value),
                    };
                }

                return { ...item, [field]: value };
            }),
        );
    };

    return (
        <>
            <Head title="Create purchase order" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Create purchase order
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Unit costs are entered in {currency} minor units
                            (e.g. cents).
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href="/purchase-orders">
                            Back to purchase orders
                        </Link>
                    </Button>
                </div>

                <form
                    className="mx-auto w-full max-w-3xl space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            supplier_id: Number(data.supplier_id),
                            branch_id: Number(data.branch_id),
                            expected_at: data.expected_at || null,
                            items: data.items.map((item) => ({
                                product_id: Number(item.product_id),
                                quantity_ordered: Number(
                                    item.quantity_ordered,
                                ),
                                unit_cost: Number(item.unit_cost),
                            })),
                        }));
                        form.post('/purchase-orders');
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="supplier_id">Supplier</Label>
                            <select
                                id="supplier_id"
                                value={form.data.supplier_id}
                                onChange={(event) =>
                                    form.setData(
                                        'supplier_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">Select supplier</option>
                                {suppliers.map((supplier) => (
                                    <option
                                        key={supplier.id}
                                        value={supplier.id}
                                    >
                                        {supplier.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.supplier_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="branch_id">Branch</Label>
                            <select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'branch_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">Select branch</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.branch_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="expected_at">
                                Expected date (optional)
                            </Label>
                            <Input
                                id="expected_at"
                                type="date"
                                value={form.data.expected_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.expected_at} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            placeholder="Optional order notes"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-medium">
                                Line items
                            </h2>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addLine}
                            >
                                <Plus className="size-4" />
                                Add line
                            </Button>
                        </div>
                        <InputError message={form.errors.items} />

                        <div className="space-y-3">
                            {form.data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 rounded-lg border border-border/80 p-3 sm:grid-cols-[1fr_6rem_7rem_auto]"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor={`product-${index}`}>
                                            Product
                                        </Label>
                                        <select
                                            id={`product-${index}`}
                                            value={item.product_id}
                                            onChange={(event) =>
                                                updateLine(
                                                    index,
                                                    'product_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">
                                                Select product
                                            </option>
                                            {products.map((product) => (
                                                <option
                                                    key={product.id}
                                                    value={product.id}
                                                >
                                                    {product.name}
                                                    {product.sku
                                                        ? ` (${product.sku})`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${index}.product_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`quantity-${index}`}>
                                            Qty
                                        </Label>
                                        <Input
                                            id={`quantity-${index}`}
                                            type="number"
                                            min={1}
                                            value={item.quantity_ordered}
                                            onChange={(event) =>
                                                updateLine(
                                                    index,
                                                    'quantity_ordered',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${index}.quantity_ordered`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`unit-cost-${index}`}>
                                            Unit cost
                                        </Label>
                                        <Input
                                            id={`unit-cost-${index}`}
                                            type="number"
                                            min={0}
                                            value={item.unit_cost}
                                            onChange={(event) =>
                                                updateLine(
                                                    index,
                                                    'unit_cost',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${index}.unit_cost`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                            onClick={() => removeLine(index)}
                                            aria-label="Remove line"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href="/purchase-orders">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            Create purchase order
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
