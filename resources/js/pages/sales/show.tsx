import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import {
    index as salesIndex,
    invoice,
    receipt,
    returns,
    voidMethod,
} from '@/routes/sales';
import { reprint } from '@/routes/sales/receipt';

type SaleDetail = {
    id: number;
    sale_number: string;
    status: string;
    payment_method_label: string | null;
    branch_name: string | null;
    cashier_name: string | null;
    customer_name: string | null;
    notes: string | null;
    void_reason: string | null;
    voided_by_name: string | null;
    approved_by_name: string | null;
    subtotal_formatted: string;
    discount_amount_formatted: string;
    total_formatted: string;
    cash_tendered_formatted: string;
    change_given_formatted: string;
    cash_tendered_minor: number;
    change_given_minor: number;
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
        returned_quantity: number;
        returnable_quantity: number;
        unit_price_formatted: string;
        list_unit_price_formatted: string;
        unit_cost_formatted: string;
        line_total_formatted: string;
    }>;
    payments: Array<{
        id: number;
        method_label: string;
        amount_formatted: string;
        amount_minor: number;
        reference: string | null;
        notes: string | null;
        received_by_name: string | null;
    }>;
    returns: Array<{
        id: number;
        return_number: string;
        total_formatted: string;
        refund_method: string;
        reason: string;
        created_at: string | null;
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

type PaymentOption = { value: string; label: string };

function newRequestId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function SalesShow({
    sale,
    activity,
    paymentMethods,
    permissions,
}: {
    sale: SaleDetail;
    activity: ActivityRow[];
    paymentMethods: PaymentOption[];
    permissions: {
        void: boolean;
        return: boolean;
        reprint: boolean;
        approve_self: boolean;
    };
}) {
    const { t } = useTranslations();
    const [voidOpen, setVoidOpen] = useState(false);
    const [returnOpen, setReturnOpen] = useState(false);
    const voidForm = useForm({ reason: '' });
    const returnForm = useForm({
        reason: '',
        refund_method: paymentMethods[0]?.value ?? 'cash',
        client_request_id: newRequestId(),
        manager_approval: {
            login: '',
            password: '',
        },
        items: sale.items
            .filter((item) => item.returnable_quantity > 0)
            .map((item) => ({
                sale_item_id: item.id,
                quantity: 0,
                max: item.returnable_quantity,
                name: item.product_name,
            })),
    });

    const reprintForm = useForm({});

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
                            <Link href={salesIndex()}>
                                {t('pages.sales.back', 'Back')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={receipt.url(sale.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {t('pages.sales.print_receipt', 'Print receipt')}
                            </a>
                        </Button>
                        {permissions.reprint ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    reprintForm.post(reprint.url(sale.id))
                                }
                                disabled={reprintForm.processing}
                            >
                                {t(
                                    'pages.sales.reprint_receipt',
                                    'Reprint receipt',
                                )}
                            </Button>
                        ) : null}
                        <Button variant="outline" asChild>
                            <a href={invoice.url(sale.id)}>
                                {t('pages.sales.download_pdf', 'Download PDF')}
                            </a>
                        </Button>
                        {permissions.return && sale.status === 'completed' ? (
                            <Button
                                variant="outline"
                                onClick={() => setReturnOpen(true)}
                            >
                                {t('pages.sales.partial_return', 'Partial return')}
                            </Button>
                        ) : null}
                        {permissions.void ? (
                            <Button
                                variant="destructive"
                                onClick={() => setVoidOpen(true)}
                            >
                                {t('pages.sales.void_sale', 'Void sale')}
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <section className="rounded-2xl border border-border/80 p-4">
                        <h2 className="mb-3 font-display text-lg font-semibold">
                            {t('pages.sales.items', 'Items')}
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="pb-2 font-medium">
                                            {t('pages.sales.product', 'Product')}
                                        </th>
                                        <th className="pb-2 font-medium">
                                            {t('pages.sales.qty', 'Qty')}
                                        </th>
                                        <th className="pb-2 font-medium">
                                            {t('pages.sales.price', 'Price')}
                                        </th>
                                        <th className="pb-2 font-medium">
                                            {t('pages.sales.cost', 'Cost')}
                                        </th>
                                        <th className="pb-2 font-medium">
                                            {t('pages.sales.total', 'Total')}
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
                                                    {item.returned_quantity > 0
                                                        ? ` · returned ${item.returned_quantity}`
                                                        : ''}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                {item.quantity}
                                            </td>
                                            <td className="py-3">
                                                {item.unit_price_formatted}
                                            </td>
                                            <td className="py-3 text-muted-foreground">
                                                {item.unit_cost_formatted}
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
                                {t('pages.sales.summary', 'Summary')}
                            </h2>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt>{t('pages.sales.customer', 'Customer')}</dt>
                                    <dd>
                                        {sale.customer?.name ??
                                            sale.customer_name ??
                                            t('pages.pos.walk_in', 'Walk-in')}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>{t('pages.sales.payment', 'Payment')}</dt>
                                    <dd>{sale.payment_method_label}</dd>
                                </div>
                                {sale.approved_by_name ? (
                                    <div className="flex justify-between">
                                        <dt>
                                            {t(
                                                'pages.sales.approved_by',
                                                'Approved by',
                                            )}
                                        </dt>
                                        <dd>{sale.approved_by_name}</dd>
                                    </div>
                                ) : null}
                                <div className="flex justify-between">
                                    <dt>{t('pages.pos.subtotal', 'Subtotal')}</dt>
                                    <dd>{sale.subtotal_formatted}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>{t('pages.pos.discount', 'Discount')}</dt>
                                    <dd>{sale.discount_amount_formatted}</dd>
                                </div>
                                <div className="flex justify-between text-base font-semibold">
                                    <dt>{t('pages.pos.total', 'Total')}</dt>
                                    <dd>{sale.total_formatted}</dd>
                                </div>
                                {sale.cash_tendered_minor > 0 ? (
                                    <>
                                        <div className="flex justify-between">
                                            <dt>
                                                {t(
                                                    'pages.pos.cash_tendered',
                                                    'Cash tendered',
                                                )}
                                            </dt>
                                            <dd>
                                                {sale.cash_tendered_formatted}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>
                                                {t('pages.pos.change', 'Change')}
                                            </dt>
                                            <dd>
                                                {sale.change_given_formatted}
                                            </dd>
                                        </div>
                                    </>
                                ) : null}
                            </dl>
                            {sale.notes ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {t('pages.sales.notes', 'Notes')}:{' '}
                                    {sale.notes}
                                </p>
                            ) : null}
                            {sale.status === 'voided' ? (
                                <p className="mt-3 text-sm text-destructive">
                                    {t('pages.sales.voided_by', 'Voided by')}{' '}
                                    {sale.voided_by_name}: {sale.void_reason}
                                </p>
                            ) : null}
                        </section>

                        <section className="rounded-2xl border border-border/80 p-4">
                            <h2 className="mb-3 font-display text-lg font-semibold">
                                {t('pages.sales.payments', 'Payments')}
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
                                                {payment.notes
                                                    ? ` · ${payment.notes}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <div
                                            className={`font-medium ${payment.amount_minor < 0 ? 'text-destructive' : ''}`}
                                        >
                                            {payment.amount_formatted}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        {sale.returns.length > 0 ? (
                            <section className="rounded-2xl border border-border/80 p-4">
                                <h2 className="mb-3 font-display text-lg font-semibold">
                                    {t('pages.sales.returns', 'Returns')}
                                </h2>
                                <div className="space-y-2 text-sm">
                                    {sale.returns.map((row) => (
                                        <div
                                            key={row.id}
                                            className="rounded-xl bg-muted/40 px-3 py-2"
                                        >
                                            <div className="font-medium">
                                                {row.return_number} ·{' '}
                                                {row.total_formatted}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {row.reason}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-2xl border border-border/80 p-4">
                        <h2 className="mb-3 font-display text-lg font-semibold">
                            {t('pages.sales.stock_movements', 'Stock movements')}
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
                            {t('pages.sales.activity', 'Activity')}
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
                                {t('pages.sales.void_sale', 'Void sale')}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'pages.sales.void_hint',
                                    'Stock will be restored and the sale marked voided.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="void_reason">
                                {t('pages.sales.reason', 'Reason')}
                            </Label>
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
                                {t('pages.pos.cancel', 'Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={voidForm.processing}
                            >
                                {voidForm.processing
                                    ? t('pages.sales.voiding', 'Voiding…')
                                    : t(
                                          'pages.sales.confirm_void',
                                          'Confirm void',
                                      )}
                            </Button>
                        </div>
                    </form>
                </div>
            ) : null}

            {returnOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
                    <form
                        className="max-h-[90vh] w-full max-w-lg space-y-4 overflow-auto rounded-2xl bg-background p-5 shadow-xl"
                        onSubmit={(event) => {
                            event.preventDefault();
                            returnForm.transform((data) => ({
                                reason: data.reason,
                                refund_method: data.refund_method,
                                client_request_id: data.client_request_id,
                                manager_approval: permissions.approve_self
                                    ? null
                                    : data.manager_approval,
                                items: data.items
                                    .filter((item) => item.quantity > 0)
                                    .map((item) => ({
                                        sale_item_id: item.sale_item_id,
                                        quantity: item.quantity,
                                    })),
                            }));
                            returnForm.post(returns.url(sale.id), {
                                onSuccess: () => {
                                    setReturnOpen(false);
                                    returnForm.setData(
                                        'client_request_id',
                                        newRequestId(),
                                    );
                                },
                            });
                        }}
                    >
                        <div>
                            <h3 className="font-display text-xl font-semibold">
                                {t(
                                    'pages.sales.partial_return',
                                    'Partial return',
                                )}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'pages.sales.return_hint',
                                    'Restore stock for selected quantities and record a refund.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-2">
                            {returnForm.data.items.map((item, index) => (
                                <div
                                    key={item.sale_item_id}
                                    className="flex items-center justify-between gap-3 rounded-xl border border-border/70 p-3"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {item.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            max {item.max}
                                        </div>
                                    </div>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={item.max}
                                        value={item.quantity}
                                        onChange={(event) => {
                                            const next = [...returnForm.data.items];
                                            next[index] = {
                                                ...item,
                                                quantity: Math.min(
                                                    item.max,
                                                    Math.max(
                                                        0,
                                                        Number(
                                                            event.target.value ||
                                                                0,
                                                        ),
                                                    ),
                                                ),
                                            };
                                            returnForm.setData('items', next);
                                        }}
                                        className="h-10 w-20 rounded-xl text-center"
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="refund_method">
                                {t(
                                    'pages.sales.refund_method',
                                    'Refund method',
                                )}
                            </Label>
                            <select
                                id="refund_method"
                                value={returnForm.data.refund_method}
                                onChange={(event) =>
                                    returnForm.setData(
                                        'refund_method',
                                        event.target.value,
                                    )
                                }
                                className="h-11 w-full rounded-xl border border-input bg-background px-3"
                            >
                                {paymentMethods.map((method) => (
                                    <option
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="return_reason">
                                {t('pages.sales.reason', 'Reason')}
                            </Label>
                            <textarea
                                id="return_reason"
                                value={returnForm.data.reason}
                                onChange={(event) =>
                                    returnForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="min-h-20 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm"
                                required
                            />
                            <InputError message={returnForm.errors.reason} />
                        </div>
                        {!permissions.approve_self ? (
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label>
                                        {t(
                                            'pages.pos.manager_login',
                                            'Manager login',
                                        )}
                                    </Label>
                                    <Input
                                        value={
                                            returnForm.data.manager_approval
                                                .login
                                        }
                                        onChange={(event) =>
                                            returnForm.setData(
                                                'manager_approval',
                                                {
                                                    ...returnForm.data
                                                        .manager_approval,
                                                    login: event.target.value,
                                                },
                                            )
                                        }
                                        className="h-11 rounded-xl"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>
                                        {t(
                                            'pages.pos.manager_password',
                                            'Password',
                                        )}
                                    </Label>
                                    <Input
                                        type="password"
                                        value={
                                            returnForm.data.manager_approval
                                                .password
                                        }
                                        onChange={(event) =>
                                            returnForm.setData(
                                                'manager_approval',
                                                {
                                                    ...returnForm.data
                                                        .manager_approval,
                                                    password:
                                                        event.target.value,
                                                },
                                            )
                                        }
                                        className="h-11 rounded-xl"
                                    />
                                </div>
                            </div>
                        ) : null}
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReturnOpen(false)}
                            >
                                {t('pages.pos.cancel', 'Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={returnForm.processing}
                            >
                                {returnForm.processing
                                    ? t(
                                          'pages.sales.processing_return',
                                          'Processing…',
                                      )
                                    : t(
                                          'pages.sales.confirm_return',
                                          'Confirm return',
                                      )}
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
