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
    description: string;
    quantity: string;
    unit_cost: string;
};

export default function SupplierInvoicesCreate({
    suppliers,
    branches,
    products,
    currency,
}: {
    suppliers: Option[];
    branches: Option[];
    products: ProductOption[];
    currency: string;
}) {
    const form = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        branch_id: '',
        supplier_invoice_number: '',
        invoice_date: '',
        due_date: '',
        tax_total: '0',
        notes: '',
        items: [
            { product_id: '', description: '', quantity: '1', unit_cost: '0' },
        ] as LineItem[],
    });

    const productCost = (productId: string): string => {
        const product = products.find((p) => String(p.id) === productId);

        return product?.cost_price != null ? String(product.cost_price) : '0';
    };

    const addLine = () => {
        form.setData('items', [
            ...form.data.items,
            { product_id: '', description: '', quantity: '1', unit_cost: '0' },
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
                        unit_cost: value ? productCost(value) : item.unit_cost,
                    };
                }

                return { ...item, [field]: value };
            }),
        );
    };

    const lineTotal = (item: LineItem): number =>
        (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0);

    const subtotal = form.data.items.reduce(
        (sum, item) => sum + lineTotal(item),
        0,
    );
    const total = subtotal + (Number(form.data.tax_total) || 0);

    return (
        <>
            <Head title="Create supplier invoice" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Create supplier invoice
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Amounts are entered in {currency} minor units.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href="/supplier-invoices">
                            Back to invoices
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
                            branch_id: data.branch_id
                                ? Number(data.branch_id)
                                : null,
                            invoice_date: data.invoice_date || null,
                            due_date: data.due_date || null,
                            tax_total: Number(data.tax_total) || 0,
                            items: data.items.map((item) => ({
                                product_id: item.product_id
                                    ? Number(item.product_id)
                                    : null,
                                description: item.description || null,
                                quantity: Number(item.quantity),
                                unit_cost: Number(item.unit_cost),
                            })),
                        }));
                        form.post('/supplier-invoices');
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
                            <Label htmlFor="branch_id">
                                Branch (optional)
                            </Label>
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
                                <option value="">No specific branch</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.branch_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="supplier_invoice_number">
                                Supplier invoice #
                            </Label>
                            <Input
                                id="supplier_invoice_number"
                                value={form.data.supplier_invoice_number}
                                onChange={(event) =>
                                    form.setData(
                                        'supplier_invoice_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={
                                    form.errors.supplier_invoice_number
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="invoice_date">Invoice date</Label>
                            <Input
                                id="invoice_date"
                                type="date"
                                value={form.data.invoice_date}
                                onChange={(event) =>
                                    form.setData(
                                        'invoice_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.invoice_date} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="due_date">Due date</Label>
                            <Input
                                id="due_date"
                                type="date"
                                value={form.data.due_date}
                                onChange={(event) =>
                                    form.setData(
                                        'due_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.due_date} />
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
                            placeholder="Optional notes"
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
                                    className="grid gap-2 rounded-lg border border-border/80 p-3 sm:grid-cols-[1fr_1fr_5rem_6rem_auto]"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor={`product-${index}`}>
                                            Product (optional)
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
                                                No product
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
                                        <Label
                                            htmlFor={`description-${index}`}
                                        >
                                            Description
                                        </Label>
                                        <Input
                                            id={`description-${index}`}
                                            value={item.description}
                                            onChange={(event) =>
                                                updateLine(
                                                    index,
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${index}.description`
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
                                            value={item.quantity}
                                            onChange={(event) =>
                                                updateLine(
                                                    index,
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${index}.quantity`
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

                    <div className="grid gap-4 sm:max-w-xs sm:justify-self-end">
                        <div className="space-y-2">
                            <Label htmlFor="tax_total">Tax total</Label>
                            <Input
                                id="tax_total"
                                type="number"
                                min={0}
                                value={form.data.tax_total}
                                onChange={(event) =>
                                    form.setData(
                                        'tax_total',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.tax_total} />
                        </div>
                        <dl className="space-y-1 rounded-lg border border-border/80 p-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="tabular-nums">{subtotal}</dd>
                            </div>
                            <div className="flex justify-between font-medium">
                                <dt>Total</dt>
                                <dd className="tabular-nums">{total}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href="/supplier-invoices">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            Create invoice
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
