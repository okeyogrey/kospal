import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';

type Option = { id: number; name: string };

type SaleOption = {
    id: number;
    sale_number: string;
    customer_id: number;
    customer_name: string | null;
    total: number;
    amount_due: number;
    amount_due_formatted: string;
    created_at: string | null;
};

type MethodOption = { value: string; label: string };

type AllocationLine = {
    sale_id: string;
    amount: string;
};

export default function CustomerPaymentsCreate({
    customers,
    openSales,
    methods,
    defaultCustomerId,
    currency,
}: {
    customers: Option[];
    openSales: SaleOption[];
    methods: MethodOption[];
    defaultCustomerId: number | null;
    currency: string;
}) {
    const form = useForm({
        customer_id: String(defaultCustomerId ?? customers[0]?.id ?? ''),
        method: methods[0]?.value ?? 'cash',
        amount: '0',
        paid_at: new Date().toISOString().slice(0, 10),
        external_reference: '',
        notes: '',
        allocations: [{ sale_id: '', amount: '0' }] as AllocationLine[],
    });

    const salesForCustomer = openSales.filter(
        (sale) => String(sale.customer_id) === form.data.customer_id,
    );

    const saleAmountDue = (saleId: string): number =>
        openSales.find((sale) => String(sale.id) === saleId)?.amount_due ?? 0;

    const addLine = () => {
        form.setData('allocations', [
            ...form.data.allocations,
            { sale_id: '', amount: '0' },
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

                if (field === 'sale_id') {
                    return {
                        ...line,
                        sale_id: value,
                        amount: String(saleAmountDue(value)),
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
            <Head title="Record customer payment" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Record customer payment
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Amounts are entered in {currency} minor units.
                        </p>
                    </div>
                    <Button variant="outline" asChild className="w-full sm:w-auto">
                        <Link href="/customer-payments">Back to payments</Link>
                    </Button>
                </div>

                <form
                    className="mx-auto w-full max-w-3xl space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            customer_id: Number(data.customer_id),
                            amount: Number(data.amount),
                            allocations: data.allocations.map((line) => ({
                                sale_id: Number(line.sale_id),
                                amount: Number(line.amount),
                            })),
                        }));
                        form.post('/customer-payments');
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="customer_id">Customer</Label>
                            <select
                                id="customer_id"
                                value={form.data.customer_id}
                                onChange={(event) => {
                                    form.setData('customer_id', event.target.value);
                                    form.setData('allocations', [
                                        { sale_id: '', amount: '0' },
                                    ]);
                                }}
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">Select customer</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.customer_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="method">Method</Label>
                            <select
                                id="method"
                                value={form.data.method}
                                onChange={(event) =>
                                    form.setData('method', event.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                {methods.map((method) => (
                                    <option key={method.value} value={method.value}>
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
                                    form.setData('amount', event.target.value)
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
                                    form.setData('paid_at', event.target.value)
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
                            <InputError message={form.errors.external_reference} />
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
                                Allocate to sales
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

                        {salesForCustomer.length === 0 ? (
                            <p className="rounded-md border border-dashed border-border/80 px-3 py-4 text-center text-sm text-muted-foreground">
                                No open credit sales for this customer.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {form.data.allocations.map((line, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-2 rounded-lg border border-border/80 p-3 sm:grid-cols-[1fr_8rem_auto]"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor={`sale-${index}`}>
                                                Sale
                                            </Label>
                                            <select
                                                id={`sale-${index}`}
                                                value={line.sale_id}
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        'sale_id',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="">Select sale</option>
                                                {salesForCustomer.map((sale) => (
                                                    <option
                                                        key={sale.id}
                                                        value={sale.id}
                                                    >
                                                        {sale.sale_number} · Due{' '}
                                                        {sale.amount_due_formatted}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `allocations.${index}.sale_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`alloc-amount-${index}`}>
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
                                                    form.data.allocations.length === 1
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
                        )}

                        <p className="text-right text-sm text-muted-foreground">
                            Allocated:{' '}
                            <span className="font-medium tabular-nums text-foreground">
                                {formatMoney(allocatedTotal, currency)}
                            </span>
                        </p>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/customer-payments">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Record payment
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
