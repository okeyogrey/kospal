import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type InvoiceItem = {
    id: number;
    product_name: string | null;
    description: string | null;
    quantity: number;
    unit_cost: number;
    line_total: number;
    line_total_formatted: string;
};

type Allocation = {
    id: number;
    amount: number;
    amount_formatted: string;
    payment_reference: string | null;
    payment_id: number | null;
};

type InvoiceDetail = {
    id: number;
    reference: string | null;
    supplier_invoice_number: string | null;
    status: string;
    supplier_name: string | null;
    branch_name: string | null;
    subtotal: number;
    tax_total: number;
    total: number;
    total_formatted: string;
    amount_paid: number;
    amount_due: number;
    invoice_date: string | null;
    due_date: string | null;
    notes: string | null;
    created_by_name: string | null;
    posted_by_name: string | null;
    created_at: string | null;
    posted_at: string | null;
    items: InvoiceItem[];
    allocations: Allocation[];
};

type ActivityRow = {
    id: number;
    action: string;
    user_name: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    posted: 'default',
    partially_paid: 'outline',
    paid: 'outline',
    void: 'destructive',
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function SupplierInvoicesShow({
    invoice,
    activity,
    permissions,
    currency,
}: {
    invoice: InvoiceDetail;
    activity: ActivityRow[];
    permissions: {
        post: boolean;
        void: boolean;
    };
    currency: string;
}) {
    const postAction = (url: string, confirmMessage: string) => {
        if (!window.confirm(confirmMessage)) {
            return;
        }

        router.post(url);
    };

    return (
        <>
            <Head
                title={invoice.reference ?? `Supplier invoice #${invoice.id}`}
            />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {invoice.reference ?? `#${invoice.id}`}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[invoice.status] ??
                                    'secondary'
                                }
                            >
                                {invoice.status.replaceAll('_', ' ')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {invoice.supplier_name}
                            {invoice.branch_name
                                ? ` · ${invoice.branch_name}`
                                : ''}
                            {invoice.supplier_invoice_number
                                ? ` · Inv# ${invoice.supplier_invoice_number}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href="/supplier-invoices">
                                All invoices
                            </Link>
                        </Button>
                        {invoice.amount_due > 0 &&
                            invoice.status !== 'void' && (
                                <Button variant="outline" asChild>
                                    <Link href="/supplier-payments/create">
                                        Record payment
                                    </Link>
                                </Button>
                            )}
                        {permissions.post && (
                            <Button
                                onClick={() =>
                                    postAction(
                                        `/supplier-invoices/${invoice.id}/post`,
                                        'Post this invoice? This will make it payable.',
                                    )
                                }
                            >
                                Post
                            </Button>
                        )}
                        {permissions.void && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        `/supplier-invoices/${invoice.id}/void`,
                                        'Void this invoice?',
                                    )
                                }
                            >
                                Void
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-border/80 p-3">
                        <p className="text-xs text-muted-foreground uppercase">
                            Total
                        </p>
                        <p className="text-lg font-semibold tabular-nums">
                            {invoice.total_formatted}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border/80 p-3">
                        <p className="text-xs text-muted-foreground uppercase">
                            Paid
                        </p>
                        <p className="text-lg font-semibold tabular-nums">
                            {formatMoney(invoice.amount_paid, currency)}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border/80 p-3">
                        <p className="text-xs text-muted-foreground uppercase">
                            Due
                        </p>
                        <p className="text-lg font-semibold tabular-nums">
                            {formatMoney(invoice.amount_due, currency)}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">Details</h2>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Created by
                                    </dt>
                                    <dd>
                                        {invoice.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(invoice.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Posted
                                    </dt>
                                    <dd>
                                        {invoice.posted_by_name ?? '—'} ·{' '}
                                        {formatWhen(invoice.posted_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Invoice date
                                    </dt>
                                    <dd>{invoice.invoice_date ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Due date
                                    </dt>
                                    <dd>{invoice.due_date ?? '—'}</dd>
                                </div>
                            </dl>
                            {invoice.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {invoice.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                Line items
                            </h2>
                            <div className="divide-y rounded-lg border border-border/80">
                                {invoice.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {item.product_name ??
                                                    item.description ??
                                                    'Item'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.quantity} ×{' '}
                                                {formatMoney(
                                                    item.unit_cost,
                                                    currency,
                                                )}
                                            </p>
                                        </div>
                                        <span className="font-medium tabular-nums">
                                            {item.line_total_formatted}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <dl className="space-y-1 rounded-lg border border-border/80 p-3 text-sm sm:max-w-xs sm:justify-self-end">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Subtotal
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatMoney(
                                            invoice.subtotal,
                                            currency,
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Tax
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatMoney(
                                            invoice.tax_total,
                                            currency,
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between font-medium">
                                    <dt>Total</dt>
                                    <dd className="tabular-nums">
                                        {invoice.total_formatted}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        {invoice.allocations.length > 0 && (
                            <section className="space-y-3">
                                <h2 className="text-sm font-medium">
                                    Payments applied
                                </h2>
                                <div className="divide-y rounded-lg border border-border/80">
                                    {invoice.allocations.map((alloc) => (
                                        <div
                                            key={alloc.id}
                                            className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                        >
                                            {alloc.payment_id ? (
                                                <Link
                                                    href={`/supplier-payments/${alloc.payment_id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {alloc.payment_reference ??
                                                        `Payment #${alloc.payment_id}`}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {alloc.payment_reference ??
                                                        'Payment'}
                                                </span>
                                            )}
                                            <span className="tabular-nums">
                                                {alloc.amount_formatted}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>

                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">
                            Activity history
                        </h2>
                        {activity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No activity recorded yet.
                            </p>
                        ) : (
                            <ol className="space-y-3 border-l border-border pl-4">
                                {activity.map((row) => (
                                    <li key={row.id} className="relative">
                                        <span className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-foreground" />
                                        <p className="text-sm font-medium">
                                            {row.action.replaceAll('_', ' ')}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.user_name ?? 'System'} ·{' '}
                                            {formatWhen(row.created_at)}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
