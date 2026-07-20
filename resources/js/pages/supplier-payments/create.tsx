import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';

type Option = { id: number; name: string };

type InvoiceOption = {
    id: number;
    reference: string | null;
    supplier_id: number;
    supplier_name: string | null;
    total: number;
    amount_due: number;
    amount_due_formatted: string;
};

type MethodOption = { value: string; label: string };

type AllocationLine = {
    supplier_invoice_id: string;
    amount: string;
};

export default function SupplierPaymentsCreate({
    suppliers,
    openInvoices,
    methods,
    defaultSupplierId,
    currency,
}: {
    suppliers: Option[];
    openInvoices: InvoiceOption[];
    methods: MethodOption[];
    defaultSupplierId: number | null;
    currency: string;
}) {
    const form = useForm({
        supplier_id: String(defaultSupplierId ?? suppliers[0]?.id ?? ''),
        method: methods[0]?.value ?? 'cash',
        amount: '0',
        paid_at: new Date().toISOString().slice(0, 10),
        external_reference: '',
        notes: '',
        allocations: [
            { supplier_invoice_id: '', amount: '0' },
        ] as AllocationLine[],
    });

    const invoicesForSupplier = openInvoices.filter(
        (invoice) => String(invoice.supplier_id) === form.data.supplier_id,
    );

    const invoiceAmountDue = (invoiceId: string): number =>
        openInvoices.find((invoice) => String(invoice.id) === invoiceId)
            ?.amount_due ?? 0;

    const addLine = () => {
        form.setData('allocations', [
            ...form.data.allocations,
            { supplier_invoice_id: '', amount: '0' },
        ]);
    };

    const removeLine = (index: number) => {
        form.setData(
            'allocations',
            form.data.allocations.filter((_, i) => i !== index),
        );
    };

    const updateLine = (
        index: number,
        field: keyof AllocationLine,
        value: string,
    ) => {
        form.setData(
            'allocations',
            form.data.allocations.map((line, i) => {
                if (i !== index) {
                    return line;
                }

                if (field === 'supplier_invoice_id') {
                    return {
                        ...line,
                        supplier_invoice_id: value,
                        amount: String(invoiceAmountDue(value)),
                    };
                }

                return { ...line, [field]: value };
            }),
        );
    };

    const allocatedTotal = form.data.allocations.reduce(
        (sum, line) => sum + (Number(line.amount) || 0),
        0,
    );

    return (
        <>
            <Head title="Record supplier payment" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Record supplier payment
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
                        <Link href="/supplier-payments">
                            Back to payments
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
                            amount: Number(data.amount),
                            allocations: data.allocations.map((line) => ({
                                supplier_invoice_id: Number(
                                    line.supplier_invoice_id,
                                ),
                                amount: Number(line.amount),
                            })),
                        }));
                        form.post('/supplier-payments');
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="supplier_id">Supplier</Label>
                            <select
                                id="supplier_id"
                                value={form.data.supplier_id}
                                onChange={(event) => {
                                    form.setData(
                                        'supplier_id',
                                        event.target.value,
                                    );
                                    form.setData('allocations', [
                                        { supplier_invoice_id: '', amount: '0' },
                                    ]);
                                }}
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
                            <Label htmlFor="method">Method</Label>
                            <select
                                id="method"
                                value={form.data.method}
                                onChange={(event) =>
                                    form.setData(
                                        'method',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                {methods.map((method) => (
                                    <option
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.method} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="amount">Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                min={1}
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData(
                                        'amount',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="paid_at">Paid on</Label>
                            <Input
                                id="paid_at"
                                type="date"
                                value={form.data.paid_at}
                                onChange={(event) =>
                                    form.setData(
                                        'paid_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.paid_at} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="external_reference">
                                External reference
                            </Label>
                            <Input
                                id="external_reference"
                                value={form.data.external_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'external_reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.external_reference}
                            />
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
                                Allocate to invoices
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
                        <InputError message={form.errors.allocations} />

                        {invoicesForSupplier.length === 0 ? (
                            <p className="rounded-md border border-dashed border-border/80 px-3 py-4 text-center text-sm text-muted-foreground">
                                No open invoices for this supplier.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {form.data.allocations.map((line, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-2 rounded-lg border border-border/80 p-3 sm:grid-cols-[1fr_8rem_auto]"
                                    >
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`invoice-${index}`}
                                            >
                                                Invoice
                                            </Label>
                                            <select
                                                id={`invoice-${index}`}
                                                value={
                                                    line.supplier_invoice_id
                                                }
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        'supplier_invoice_id',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select invoice
                                                </option>
                                                {invoicesForSupplier.map(
                                                    (invoice) => (
                                                        <option
                                                            key={invoice.id}
                                                            value={invoice.id}
                                                        >
                                                            {invoice.reference ??
                                                                `#${invoice.id}`}{' '}
                                                            · Due{' '}
                                                            {
                                                                invoice.amount_due_formatted
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `allocations.${index}.supplier_invoice_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`alloc-amount-${index}`}
                                            >
                                                Amount
                                            </Label>
                                            <Input
                                                id={`alloc-amount-${index}`}
                                                type="number"
                                                min={1}
                                                value={line.amount}
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        'amount',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `allocations.${index}.amount`
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
                                                    form.data.allocations
                                                        .length === 1
                                                }
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                                aria-label="Remove line"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        <p className="text-right text-sm text-muted-foreground">
                            Allocated:{' '}
                            <span className="font-medium tabular-nums text-foreground">
                                {formatMoney(allocatedTotal, currency)}
                            </span>
                        </p>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href="/supplier-payments">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            Record payment
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
