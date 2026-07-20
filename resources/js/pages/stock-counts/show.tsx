import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CountItem = {
    id: number;
    product_id: number;
    product_name: string | null;
    sku: string | null;
    system_quantity: number;
    counted_quantity: number | null;
    variance: number | null;
};

type Movement = {
    id: number;
    type: string;
    quantity_delta: number;
    product_name: string | null;
    user_name: string | null;
    created_at: string | null;
};

type CountDetail = {
    id: number;
    reference: string | null;
    status: string;
    branch_name: string | null;
    notes: string | null;
    created_by_name: string | null;
    completed_by_name: string | null;
    created_at: string | null;
    completed_at: string | null;
    items: CountItem[];
    movements: Movement[];
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
    in_progress: 'default',
    completed: 'outline',
    cancelled: 'destructive',
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function StockCountsShow({
    count,
    activity,
    permissions,
}: {
    count: CountDetail;
    activity: ActivityRow[];
    permissions: {
        update: boolean;
        complete: boolean;
        cancel: boolean;
    };
}) {
    const [counted, setCounted] = useState<Record<number, string>>(() => {
        const initial: Record<number, string> = {};

        count.items.forEach((item) => {
            initial[item.product_id] = String(
                item.counted_quantity ?? item.system_quantity,
            );
        });

        return initial;
    });
    const [saving, setSaving] = useState(false);

    const variance = (item: CountItem): number => {
        const value = Number(counted[item.product_id] ?? 0);

        return value - item.system_quantity;
    };

    const startCount = () => {
        if (!window.confirm('Start this stock count?')) {
            return;
        }

        router.post(`/stock-counts/${count.id}/start`);
    };

    const saveCounts = () => {
        setSaving(true);
        router.post(
            `/stock-counts/${count.id}/record`,
            {
                counts: count.items.map((item) => ({
                    product_id: item.product_id,
                    counted_quantity: Number(
                        counted[item.product_id] ?? item.system_quantity,
                    ),
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const completeCount = () => {
        if (
            !window.confirm(
                'Complete this stock count? Variances will be posted as stock adjustments.',
            )
        ) {
            return;
        }

        router.post(`/stock-counts/${count.id}/complete`);
    };

    const cancelCount = () => {
        if (!window.confirm('Cancel this stock count?')) {
            return;
        }

        router.post(`/stock-counts/${count.id}/cancel`);
    };

    const editable =
        permissions.update &&
        (count.status === 'draft' || count.status === 'in_progress');

    return (
        <>
            <Head title={count.reference ?? `Stock count #${count.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {count.reference ?? `#${count.id}`}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[count.status] ?? 'secondary'
                                }
                            >
                                {count.status.replaceAll('_', ' ')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {count.branch_name}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href="/stock-counts">All stock counts</Link>
                        </Button>
                        {permissions.update && count.status === 'draft' && (
                            <Button onClick={startCount}>Start count</Button>
                        )}
                        {editable && (
                            <Button onClick={saveCounts} disabled={saving}>
                                Save counts
                            </Button>
                        )}
                        {permissions.complete &&
                            count.status === 'in_progress' && (
                                <Button
                                    variant="outline"
                                    onClick={completeCount}
                                >
                                    Complete count
                                </Button>
                            )}
                        {permissions.cancel &&
                            count.status !== 'completed' &&
                            count.status !== 'cancelled' && (
                                <Button
                                    variant="destructive"
                                    onClick={cancelCount}
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
                                        {count.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(count.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Completed
                                    </dt>
                                    <dd>
                                        {count.completed_by_name ?? '—'} ·{' '}
                                        {formatWhen(count.completed_at)}
                                    </dd>
                                </div>
                            </dl>
                            {count.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {count.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-medium">
                                    Items
                                </h2>
                                {editable ? (
                                    <p className="text-xs text-muted-foreground">
                                        Enter counted quantities, then save.
                                    </p>
                                ) : null}
                            </div>
                            <div className="divide-y rounded-lg border border-border/80">
                                <div className="grid grid-cols-[1fr_5rem_6rem_5rem] gap-2 px-3 py-2 text-xs font-medium text-muted-foreground">
                                    <span>Product</span>
                                    <span className="text-right">System</span>
                                    <span className="text-right">
                                        Counted
                                    </span>
                                    <span className="text-right">
                                        Variance
                                    </span>
                                </div>
                                {count.items.map((item) => {
                                    const varianceValue = editable
                                        ? variance(item)
                                        : item.variance ?? 0;

                                    return (
                                        <div
                                            key={item.id}
                                            className="grid grid-cols-[1fr_5rem_6rem_5rem] items-center gap-2 px-3 py-2 text-sm"
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
                                            <span className="text-right tabular-nums">
                                                {item.system_quantity}
                                            </span>
                                            {editable ? (
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={
                                                        counted[
                                                            item.product_id
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setCounted(
                                                            (prev) => ({
                                                                ...prev,
                                                                [item.product_id]:
                                                                    event
                                                                        .target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    className="h-8 text-right"
                                                />
                                            ) : (
                                                <span className="text-right tabular-nums">
                                                    {item.counted_quantity ??
                                                        '—'}
                                                </span>
                                            )}
                                            <span
                                                className={
                                                    'text-right font-medium tabular-nums ' +
                                                    (varianceValue === 0
                                                        ? 'text-muted-foreground'
                                                        : varianceValue > 0
                                                          ? 'text-emerald-600'
                                                          : 'text-destructive')
                                                }
                                            >
                                                {varianceValue > 0 ? '+' : ''}
                                                {varianceValue}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>

                        {count.movements.length > 0 && (
                            <section className="space-y-3">
                                <h2 className="text-sm font-medium">
                                    Stock movements
                                </h2>
                                <div className="space-y-2">
                                    {count.movements.map((movement) => (
                                        <div
                                            key={movement.id}
                                            className="rounded-lg border border-border/80 px-3 py-2 text-sm"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-medium">
                                                    {movement.type}
                                                </span>
                                                <span className="tabular-nums">
                                                    {movement.quantity_delta > 0
                                                        ? '+'
                                                        : ''}
                                                    {movement.quantity_delta}
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {movement.product_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {movement.user_name} ·{' '}
                                                {formatWhen(
                                                    movement.created_at,
                                                )}
                                            </p>
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
