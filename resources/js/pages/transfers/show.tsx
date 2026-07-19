import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    cancel,
    dispatch,
    index as transfersIndex,
    receive,
} from '@/routes/stock-transfers';

type TransferDetail = {
    id: number;
    reference: string | null;
    status: string;
    source_branch_name: string | null;
    destination_branch_name: string | null;
    notes: string | null;
    items: Array<{
        id: number;
        product_name: string | null;
        sku: string | null;
        quantity: number;
    }>;
    created_by_name: string | null;
    dispatched_by_name: string | null;
    received_by_name: string | null;
    cancelled_by_name: string | null;
    created_at: string | null;
    dispatched_at: string | null;
    received_at: string | null;
    cancelled_at: string | null;
    movements: Array<{
        id: number;
        type: string;
        quantity_delta: number;
        quantity_before: number;
        quantity_after: number;
        branch_name: string | null;
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
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    dispatched: 'default',
    received: 'outline',
    cancelled: 'destructive',
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function TransfersShow({
    transfer,
    activity,
    permissions,
}: {
    transfer: TransferDetail;
    activity: ActivityRow[];
    permissions: {
        dispatch: boolean;
        receive: boolean;
        cancel: boolean;
    };
}) {
    const postAction = (url: string, confirmMessage: string) => {
        if (!window.confirm(confirmMessage)) {
            return;
        }

        router.post(url);
    };

    return (
        <>
            <Head title={transfer.reference ?? `Transfer #${transfer.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {transfer.reference ?? `#${transfer.id}`}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[transfer.status] ??
                                    'secondary'
                                }
                            >
                                {transfer.status}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {transfer.source_branch_name} →{' '}
                            {transfer.destination_branch_name}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href={transfersIndex()}>All transfers</Link>
                        </Button>
                        {permissions.dispatch && (
                            <Button
                                onClick={() =>
                                    postAction(
                                        dispatch.url(transfer),
                                        'Dispatch this transfer? Stock will leave the source branch.',
                                    )
                                }
                            >
                                Dispatch
                            </Button>
                        )}
                        {permissions.receive && (
                            <Button
                                onClick={() =>
                                    postAction(
                                        receive.url(transfer),
                                        'Receive this transfer? Stock will be added to the destination branch.',
                                    )
                                }
                            >
                                Receive
                            </Button>
                        )}
                        {permissions.cancel && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        cancel.url(transfer),
                                        'Cancel this draft transfer?',
                                    )
                                }
                            >
                                Cancel draft
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
                                        {transfer.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(transfer.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Dispatched
                                    </dt>
                                    <dd>
                                        {transfer.dispatched_by_name ?? '—'} ·{' '}
                                        {formatWhen(transfer.dispatched_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Received
                                    </dt>
                                    <dd>
                                        {transfer.received_by_name ?? '—'} ·{' '}
                                        {formatWhen(transfer.received_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Cancelled
                                    </dt>
                                    <dd>
                                        {transfer.cancelled_by_name ?? '—'} ·{' '}
                                        {formatWhen(transfer.cancelled_at)}
                                    </dd>
                                </div>
                            </dl>
                            {transfer.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {transfer.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">Items</h2>
                            <div className="divide-y rounded-lg border border-border/80">
                                {transfer.items.map((item) => (
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
                                        <span className="font-medium tabular-nums">
                                            {item.quantity}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </section>

                        {transfer.movements.length > 0 && (
                            <section className="space-y-3">
                                <h2 className="text-sm font-medium">
                                    Stock movements
                                </h2>
                                <div className="space-y-2">
                                    {transfer.movements.map((movement) => (
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
                                                {movement.product_name} ·{' '}
                                                {movement.branch_name} ·{' '}
                                                {movement.quantity_before} →{' '}
                                                {movement.quantity_after}
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
