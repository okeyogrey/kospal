import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type OrderItem = {
    id: number;
    product_name: string | null;
    sku: string | null;
    quantity_ordered: number;
    quantity_received: number;
    unit_cost: number;
};

type OrderDetail = {
    id: number;
    reference: string | null;
    status: string;
    supplier_name: string | null;
    branch_name: string | null;
    expected_at: string | null;
    notes: string | null;
    created_by_name: string | null;
    sent_by_name: string | null;
    cancelled_by_name: string | null;
    created_at: string | null;
    sent_at: string | null;
    cancelled_at: string | null;
    items: OrderItem[];
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
    sent: 'default',
    partially_received: 'outline',
    received: 'outline',
    cancelled: 'destructive',
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function PurchaseOrdersShow({
    order,
    activity,
    permissions,
    currency,
}: {
    order: OrderDetail;
    activity: ActivityRow[];
    permissions: {
        send: boolean;
        cancel: boolean;
    };
    currency: string;
}) {
    const postAction = (url: string, confirmMessage: string) => {
        if (!window.confirm(confirmMessage)) {
            return;
        }

        router.post(url);
    };

    const canReceive =
        order.status === 'sent' || order.status === 'partially_received';

    return (
        <>
            <Head title={order.reference ?? `Purchase order #${order.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {order.reference ?? `#${order.id}`}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[order.status] ?? 'secondary'
                                }
                            >
                                {order.status.replaceAll('_', ' ')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {order.supplier_name} · {order.branch_name}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href="/purchase-orders">
                                All purchase orders
                            </Link>
                        </Button>
                        {canReceive && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/goods-received/create?purchase_order_id=${order.id}`}
                                >
                                    Receive goods
                                </Link>
                            </Button>
                        )}
                        {permissions.send && (
                            <Button
                                onClick={() =>
                                    postAction(
                                        `/purchase-orders/${order.id}/send`,
                                        'Send this purchase order to the supplier?',
                                    )
                                }
                            >
                                Send
                            </Button>
                        )}
                        {permissions.cancel && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        `/purchase-orders/${order.id}/cancel`,
                                        'Cancel this purchase order?',
                                    )
                                }
                            >
                                Cancel
                            </Button>
                        )}
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
                                        {order.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(order.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Sent
                                    </dt>
                                    <dd>
                                        {order.sent_by_name ?? '—'} ·{' '}
                                        {formatWhen(order.sent_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Expected
                                    </dt>
                                    <dd>{order.expected_at ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Cancelled
                                    </dt>
                                    <dd>
                                        {order.cancelled_by_name ?? '—'} ·{' '}
                                        {formatWhen(order.cancelled_at)}
                                    </dd>
                                </div>
                            </dl>
                            {order.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {order.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                Line items
                            </h2>
                            <div className="divide-y rounded-lg border border-border/80">
                                {order.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {item.product_name}
                                            </p>
                                            {item.sku ? (
                                                <p className="text-xs text-muted-foreground">
                                                    {item.sku}
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="text-right text-sm">
                                            <p className="tabular-nums">
                                                {item.quantity_received} /{' '}
                                                {item.quantity_ordered}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {formatMoney(
                                                    item.unit_cost,
                                                    currency,
                                                )}{' '}
                                                each
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
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
