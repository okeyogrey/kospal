import { Head, Link, router } from '@inertiajs/react';
import { Download, Wallet } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as customersIndex, show as customersShow } from '@/routes/customers';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    credit_enabled: boolean;
    credit_limit: number | null;
    credit_limit_formatted: string | null;
    payment_terms_days: number | null;
    outstanding_balance: number;
    outstanding_balance_formatted: string;
    credit_available: number | null;
    credit_available_formatted: string | null;
};

type LedgerEntry = {
    id: number;
    type: string;
    type_label: string;
    direction: string;
    amount: number;
    amount_formatted: string;
    balance_after: number;
    balance_after_formatted: string;
    description: string;
    entry_date: string;
};

type PaymentRow = {
    id: number;
    reference: string | null;
    method: string;
    amount_formatted: string;
    paid_at: string | null;
    created_by_name: string | null;
};

type PurchaseRow = {
    id: number;
    sale_number: string;
    total_formatted: string;
    amount_due: number;
    amount_due_formatted: string;
    created_at: string | null;
};

type ProductStat = {
    product_id: number;
    product_name: string;
    sku: string | null;
    total_quantity: number;
    total_spend: number;
    total_spend_formatted: string;
};

type Analytics = {
    lifetime_spend_formatted: string;
    purchase_count: number;
    average_order_value_formatted: string;
    last_purchase_at: string | null;
    most_purchased_products: ProductStat[];
};

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'ledger', label: 'Ledger' },
    { key: 'payments', label: 'Payments' },
    { key: 'purchases', label: 'Purchases' },
    { key: 'analytics', label: 'Analytics' },
] as const;

export default function CustomersShow({
    customer,
    analytics,
    ledger,
    payments,
    purchases,
    tab,
    filters,
    permissions,
    hasCreditFeature,
}: {
    customer: Customer;
    analytics: Analytics;
    ledger: LedgerEntry[];
    payments: PaymentRow[];
    purchases: PurchaseRow[];
    tab: string;
    filters: { from: string | null; to: string | null };
    permissions: { manage: boolean; recordPayments: boolean };
    hasCreditFeature: boolean;
    currency: string;
}) {
    const activeTab = tabs.some((t) => t.key === tab) ? tab : 'overview';

    const switchTab = (nextTab: string) => {
        router.get(
            customersShow.url(customer.id, { query: { tab: nextTab } }),
            {},
            { preserveState: true },
        );
    };

    const statementUrl = `/customers/${customer.id}/statement.pdf?from=${filters.from ?? ''}&to=${filters.to ?? ''}`;

    return (
        <>
            <Head title={customer.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {customer.name}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            {customer.phone ? <span>{customer.phone}</span> : null}
                            {customer.email ? <span>{customer.email}</span> : null}
                            <Badge
                                variant={
                                    customer.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {customer.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                            {customer.credit_enabled ? (
                                <Badge variant="outline">Credit enabled</Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.recordPayments && hasCreditFeature ? (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/customer-payments/create?customer_id=${customer.id}`}
                                >
                                    <Wallet className="mr-2 size-4" />
                                    Record payment
                                </Link>
                            </Button>
                        ) : null}
                        {hasCreditFeature ? (
                            <Button variant="outline" asChild>
                                <a href={statementUrl}>
                                    <Download className="mr-2 size-4" />
                                    Statement PDF
                                </a>
                            </Button>
                        ) : null}
                        <Button variant="outline" asChild>
                            <Link href={customersIndex()}>Back</Link>
                        </Button>
                    </div>
                </div>

                {hasCreditFeature ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <div className="text-xs text-muted-foreground">
                                Outstanding balance
                            </div>
                            <div className="mt-1 font-display text-xl font-semibold tabular-nums">
                                {customer.outstanding_balance_formatted}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <div className="text-xs text-muted-foreground">
                                Credit limit
                            </div>
                            <div className="mt-1 font-display text-xl font-semibold tabular-nums">
                                {customer.credit_limit_formatted ?? 'Unlimited'}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <div className="text-xs text-muted-foreground">
                                Credit available
                            </div>
                            <div className="mt-1 font-display text-xl font-semibold tabular-nums">
                                {customer.credit_available_formatted ?? '—'}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <div className="text-xs text-muted-foreground">
                                Lifetime spend
                            </div>
                            <div className="mt-1 font-display text-xl font-semibold tabular-nums">
                                {analytics.lifetime_spend_formatted}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <div className="text-xs text-muted-foreground">
                            Lifetime spend
                        </div>
                        <div className="mt-1 font-display text-xl font-semibold tabular-nums">
                            {analytics.lifetime_spend_formatted}
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap gap-2 border-b border-border/80 pb-2">
                    {tabs.map((item) => (
                        <Button
                            key={item.key}
                            type="button"
                            size="sm"
                            variant={
                                activeTab === item.key ? 'default' : 'ghost'
                            }
                            onClick={() => switchTab(item.key)}
                        >
                            {item.label}
                        </Button>
                    ))}
                </div>

                {activeTab === 'overview' ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-4 rounded-2xl border border-border/80 p-4">
                            <h2 className="font-display text-lg font-semibold">
                                Contact
                            </h2>
                            <dl className="grid gap-2 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Address
                                    </dt>
                                    <dd>{customer.address ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Notes
                                    </dt>
                                    <dd>{customer.notes ?? '—'}</dd>
                                </div>
                                {customer.payment_terms_days ? (
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Payment terms
                                        </dt>
                                        <dd>
                                            {customer.payment_terms_days} days
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>
                        </div>
                        <div className="space-y-4 rounded-2xl border border-border/80 p-4">
                            <h2 className="font-display text-lg font-semibold">
                                Recent purchases
                            </h2>
                            {purchases.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No purchases yet.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {purchases.slice(0, 5).map((purchase) => (
                                        <li
                                            key={purchase.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <Link
                                                href={`/sales/${purchase.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {purchase.sale_number}
                                            </Link>
                                            <span className="tabular-nums">
                                                {purchase.total_formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                ) : null}

                {activeTab === 'ledger' && hasCreditFeature ? (
                    <div className="space-y-4">
                        <form
                            className="flex flex-wrap gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.get(
                                    customersShow.url(customer.id, {
                                        query: {
                                            tab: 'ledger',
                                            from:
                                                String(
                                                    data.get('from') ?? '',
                                                ) || undefined,
                                            to:
                                                String(data.get('to') ?? '') ||
                                                undefined,
                                        },
                                    }),
                                    {},
                                    { preserveState: true },
                                );
                            }}
                        >
                            <Input
                                name="from"
                                type="date"
                                defaultValue={filters.from ?? ''}
                                className="w-40"
                            />
                            <Input
                                name="to"
                                type="date"
                                defaultValue={filters.to ?? ''}
                                className="w-40"
                            />
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>
                        <div className="overflow-x-auto rounded-2xl border border-border/80">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">
                                            Description
                                        </th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3 text-right">
                                            Amount
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ledger.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No ledger entries.
                                            </td>
                                        </tr>
                                    ) : (
                                        ledger.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className="border-t border-border/70"
                                            >
                                                <td className="px-4 py-3">
                                                    {entry.entry_date}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {entry.description}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {entry.type_label}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {entry.direction === 'debit'
                                                        ? '+'
                                                        : '−'}
                                                    {entry.amount_formatted}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {
                                                        entry.balance_after_formatted
                                                    }
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {activeTab === 'payments' ? (
                    <div className="overflow-x-auto rounded-2xl border border-border/80">
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3">Reference</th>
                                    <th className="px-4 py-3">Method</th>
                                    <th className="px-4 py-3 text-right">
                                        Amount
                                    </th>
                                    <th className="px-4 py-3">Paid on</th>
                                    <th className="px-4 py-3">Recorded by</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No payments recorded.
                                        </td>
                                    </tr>
                                ) : (
                                    payments.map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="border-t border-border/70"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/customer-payments/${payment.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {payment.reference ??
                                                        `#${payment.id}`}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 capitalize">
                                                {payment.method.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {payment.amount_formatted}
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.paid_at ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.created_by_name ?? '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                ) : null}

                {activeTab === 'purchases' ? (
                    <div className="overflow-x-auto rounded-2xl border border-border/80">
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3">Sale</th>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3 text-right">
                                        Total
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Due
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchases.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No purchases yet.
                                        </td>
                                    </tr>
                                ) : (
                                    purchases.map((purchase) => (
                                        <tr
                                            key={purchase.id}
                                            className="border-t border-border/70"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/sales/${purchase.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {purchase.sale_number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {purchase.created_at
                                                    ? new Date(
                                                          purchase.created_at,
                                                      ).toLocaleDateString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {purchase.total_formatted}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {purchase.amount_due > 0
                                                    ? purchase.amount_due_formatted
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                ) : null}

                {activeTab === 'analytics' ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-3 rounded-2xl border border-border/80 p-4">
                            <h2 className="font-display text-lg font-semibold">
                                Summary
                            </h2>
                            <dl className="grid gap-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Lifetime spend
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {analytics.lifetime_spend_formatted}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Purchase count
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {analytics.purchase_count}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Average order value
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {
                                            analytics.average_order_value_formatted
                                        }
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Last purchase
                                    </dt>
                                    <dd className="font-medium">
                                        {analytics.last_purchase_at
                                            ? new Date(
                                                  analytics.last_purchase_at,
                                              ).toLocaleDateString()
                                            : '—'}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div className="space-y-3 rounded-2xl border border-border/80 p-4">
                            <h2 className="font-display text-lg font-semibold">
                                Most purchased products
                            </h2>
                            {analytics.most_purchased_products.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No product history yet.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {analytics.most_purchased_products.map(
                                        (product) => (
                                            <li
                                                key={product.product_id}
                                                className="flex items-start justify-between gap-3 border-b border-border/60 pb-2 last:border-0"
                                            >
                                                <div>
                                                    <div className="font-medium">
                                                        {product.product_name}
                                                    </div>
                                                    {product.sku ? (
                                                        <div className="text-xs text-muted-foreground">
                                                            {product.sku}
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <div className="text-right">
                                                    <div className="tabular-nums">
                                                        {product.total_quantity}{' '}
                                                        sold
                                                    </div>
                                                    <div className="text-xs text-muted-foreground tabular-nums">
                                                        {
                                                            product.total_spend_formatted
                                                        }
                                                    </div>
                                                </div>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}

CustomersShow.layout = {
    breadcrumbs: (props: { customer: Customer }) => [
        { title: 'Customers', href: customersIndex() },
        {
            title: props.customer.name,
            href: customersShow(props.customer.id),
        },
    ],
};
