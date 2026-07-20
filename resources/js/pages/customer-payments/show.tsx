import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Allocation = {
    id: number;
    sale_id: number;
    sale_number: string | null;
    amount: number;
    amount_formatted: string;
};

type PaymentDetail = {
    id: number;
    reference: string | null;
    customer_name: string | null;
    customer_id: number;
    method: string;
    amount: number;
    amount_formatted: string;
    external_reference: string | null;
    notes: string | null;
    paid_at: string | null;
    created_by_name: string | null;
    created_at: string | null;
    allocations: Allocation[];
};

type ActivityRow = {
    id: number;
    action: string;
    user_name: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function CustomerPaymentsShow({
    payment,
    activity,
}: {
    payment: PaymentDetail;
    activity: ActivityRow[];
    currency: string;
}) {
    return (
        <>
            <Head title={payment.reference ?? `Payment #${payment.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <h1 className="font-display text-2xl font-semibold">
                            {payment.reference ?? `#${payment.id}`}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={`/customers/${payment.customer_id}`}
                                className="hover:underline"
                            >
                                {payment.customer_name}
                            </Link>{' '}
                            · {payment.method.replaceAll('_', ' ')}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/customer-payments">All payments</Link>
                    </Button>
                </div>

                <div className="rounded-lg border border-border/80 p-4">
                    <p className="text-xs text-muted-foreground uppercase">
                        Amount received
                    </p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {payment.amount_formatted}
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">Details</h2>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Paid on
                                    </dt>
                                    <dd>{payment.paid_at ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Recorded by
                                    </dt>
                                    <dd>
                                        {payment.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(payment.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        External reference
                                    </dt>
                                    <dd>{payment.external_reference ?? '—'}</dd>
                                </div>
                            </dl>
                            {payment.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {payment.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                Applied to sales
                            </h2>
                            <div className="divide-y rounded-lg border border-border/80">
                                {payment.allocations.map((alloc) => (
                                    <div
                                        key={alloc.id}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <Link
                                            href={`/sales/${alloc.sale_id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {alloc.sale_number ??
                                                `#${alloc.sale_id}`}
                                        </Link>
                                        <span className="tabular-nums">
                                            {alloc.amount_formatted}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">Activity history</h2>
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
