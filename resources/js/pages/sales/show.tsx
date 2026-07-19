import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    index as salesIndex,
    invoice,
    receipt,
    voidMethod,
} from '@/routes/sales';

type SaleDetail = {
    id: number;
    sale_number: string;
    status: string;
    payment_method_label: string;
    branch_name: string | null;
    cashier_name: string | null;
    customer_name: string | null;
    notes: string | null;
    void_reason: string | null;
    voided_by_name: string | null;
    subtotal_formatted: string;
    discount_amount_formatted: string;
    total_formatted: string;
    created_at: string | null;
    voided_at: string | null;
    customer: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
    } | null;
    items: Array<{
        id: number;
        product_name: string;
        sku: string;
        quantity: number;
        unit_price_formatted: string;
        line_total_formatted: string;
    }>;
    payments: Array<{
        id: number;
        method_label: string;
        amount_formatted: string;
        reference: string | null;
        received_by_name: string | null;
    }>;
    movements: Array<{
        id: number;
        type: string;
        quantity_delta: number;
        quantity_before: number;
        quantity_after: number;
        product_name: string | null;
        user_name: string | null;
        note: string | null;
        created_at: string | null;
    }>;
};

type ActivityRow = {
    id: number;
    action: string;
    user_name: string | null;
    created_at: string | null;
};

export default function SalesShow({
    sale,
    activity,
    permissions,
}: {
    sale: SaleDetail;
    activity: ActivityRow[];
    permissions: { void: boolean };
}) {
    const [voidOpen, setVoidOpen] = useState(false);
    const voidForm = useForm({ reason: '' });

    return (
        <>
            <Head title={sale.sale_number} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {sale.sale_number}
                            </h1>
                            <Badge
                                variant={
                                    sale.status === 'voided'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {sale.status}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {sale.branch_name} · {sale.cashier_name} ·{' '}
                            {sale.created_at
                                ? new Date(sale.created_at).toLocaleString()
                                : '—'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={salesIndex()}>Back</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={receipt.url(sale.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Print receipt
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={invoice.url(sale.id)}>Download PDF</a>
                        </Button>
                        {permissions.void ? (
                            <Button
                                variant="destructive"
                                onClick={() => setVoidOpen(true)}
                            >
                                Void sale
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <section className="rounded-2xl border border-border/80 p-4">
                        <h2 className="mb-3 font-display text-lg font-semibold">
                            Items
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="pb-2 font-medium">
                                            Product
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Qty
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Price
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sale.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-t border-border/70"
                                        >
                                            <td className="py-3">
                                                <div className="font-medium">
                                                    {item.product_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.sku}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                {item.quantity}
                                            </td>
                                            <td className="py-3">
                                                {item.unit_price_formatted}
                                            </td>
                                            <td className="py-3 font-medium">
                                                {item.line_total_formatted}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div className="space-y-4">
                        <section className="rounded-2xl border border-border/80 p-4">
                            <h2 className="mb-3 font-display text-lg font-semibold">
                                Summary
                            </h2>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt>Customer</dt>
                                    <dd>
                                        {sale.customer?.name ??
                                            sale.customer_name ??
                                            'Walk-in'}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Payment</dt>
                                    <dd>{sale.payment_method_label}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Subtotal</dt>
                                    <dd>{sale.subtotal_formatted}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Discount</dt>
                                    <dd>{sale.discount_amount_formatted}</dd>
                                </div>
                                <div className="flex justify-between text-base font-semibold">
                                    <dt>Total</dt>
                                    <dd>{sale.total_formatted}</dd>
                                </div>
                            </dl>
                            {sale.notes ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Notes: {sale.notes}
                                </p>
                            ) : null}
                            {sale.status === 'voided' ? (
                                <p className="mt-3 text-sm text-destructive">
                                    Voided by {sale.voided_by_name}:{' '}
                                    {sale.void_reason}
                                </p>
                            ) : null}
                        </section>

                        <section className="rounded-2xl border border-border/80 p-4">
                            <h2 className="mb-3 font-display text-lg font-semibold">
                                Payments
                            </h2>
                            <div className="space-y-2 text-sm">
                                {sale.payments.map((payment) => (
                                    <div
                                        key={payment.id}
                                        className="flex justify-between gap-3"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {payment.method_label}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {payment.received_by_name}
                                                {payment.reference
                                                    ? ` · ${payment.reference}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <div className="font-medium">
                                            {payment.amount_formatted}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-2xl border border-border/80 p-4">
                        <h2 className="mb-3 font-display text-lg font-semibold">
                            Stock movements
                        </h2>
                        <div className="space-y-2 text-sm">
                            {sale.movements.map((movement) => (
                                <div
                                    key={movement.id}
                                    className="rounded-xl bg-muted/40 px-3 py-2"
                                >
                                    <div className="font-medium">
                                        {movement.type} ·{' '}
                                        {movement.product_name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {movement.quantity_before} →{' '}
                                        {movement.quantity_after} (
                                        {movement.quantity_delta > 0 ? '+' : ''}
                                        {movement.quantity_delta}) ·{' '}
                                        {movement.user_name}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-2xl border border-border/80 p-4">
                        <h2 className="mb-3 font-display text-lg font-semibold">
                            Activity
                        </h2>
                        <div className="space-y-2 text-sm">
                            {activity.map((row) => (
                                <div
                                    key={row.id}
                                    className="rounded-xl bg-muted/40 px-3 py-2"
                                >
                                    <div className="font-medium">
                                        {row.action}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {row.user_name} ·{' '}
                                        {row.created_at
                                            ? new Date(
                                                  row.created_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>

            {voidOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
                    <form
                        className="w-full max-w-md space-y-4 rounded-2xl bg-background p-5 shadow-xl"
                        onSubmit={(event) => {
                            event.preventDefault();
                            voidForm.post(voidMethod.url(sale.id), {
                                onSuccess: () => setVoidOpen(false),
                            });
                        }}
                    >
                        <div>
                            <h3 className="font-display text-xl font-semibold">
                                Void sale
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Stock will be restored and the sale marked
                                voided.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="void_reason">Reason</Label>
                            <textarea
                                id="void_reason"
                                value={voidForm.data.reason}
                                onChange={(event) =>
                                    voidForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="min-h-24 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm"
                                required
                            />
                            <InputError message={voidForm.errors.reason} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVoidOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={voidForm.processing}
                            >
                                {voidForm.processing
                                    ? 'Voiding…'
                                    : 'Confirm void'}
                            </Button>
                        </div>
                    </form>
                </div>
            ) : null}
        </>
    );
}

SalesShow.layout = {
    breadcrumbs: [
        {
            title: 'Sales',
            href: salesIndex(),
        },
        {
            title: 'Sale',
            href: '#',
        },
    ],
};
