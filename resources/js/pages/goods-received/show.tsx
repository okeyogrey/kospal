import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type NoteItem = {
    id: number;
    product_name: string | null;
    sku: string | null;
    quantity: number;
    unit_cost: number;
};

type Movement = {
    id: number;
    type: string;
    quantity_delta: number;
    unit_cost: number | null;
    product_name: string | null;
    branch_name: string | null;
    user_name: string | null;
    created_at: string | null;
};

type NoteDetail = {
    id: number;
    reference: string | null;
    status: string;
    supplier_name: string | null;
    branch_name: string | null;
    purchase_order_reference: string | null;
    invoice_id: number | null;
    invoice_reference: string | null;
    notes: string | null;
    created_by_name: string | null;
    posted_by_name: string | null;
    created_at: string | null;
    posted_at: string | null;
    items: NoteItem[];
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
    posted: 'default',
    cancelled: 'destructive',
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function GoodsReceivedShow({
    note,
    activity,
    permissions,
    currency,
}: {
    note: NoteDetail;
    activity: ActivityRow[];
    permissions: {
        post: boolean;
        cancel: boolean;
    };
    currency: string;
}) {
    const postNote = () => {
        if (
            !window.confirm(
                'Post this goods received note? Inventory will be updated and a supplier invoice will be created.',
            )
        ) {
            return;
        }

        router.post(`/goods-received/${note.id}/post`, {
            create_invoice: true,
        });
    };

    const cancelNote = () => {
        if (!window.confirm('Cancel this goods received note?')) {
            return;
        }

        router.post(`/goods-received/${note.id}/cancel`);
    };

    return (
        <>
            <Head title={note.reference ?? `Goods received #${note.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {note.reference ?? `#${note.id}`}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[note.status] ?? 'secondary'
                                }
                            >
                                {note.status}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {note.supplier_name} · {note.branch_name}
                            {note.purchase_order_reference
                                ? ` · PO ${note.purchase_order_reference}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href="/goods-received">
                                All goods received
                            </Link>
                        </Button>
                        {note.invoice_id && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/supplier-invoices/${note.invoice_id}`}
                                >
                                    View invoice{' '}
                                    {note.invoice_reference ?? ''}
                                </Link>
                            </Button>
                        )}
                        {permissions.post && (
                            <Button onClick={postNote}>Post</Button>
                        )}
                        {permissions.cancel && (
                            <Button variant="destructive" onClick={cancelNote}>
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
                                        {note.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(note.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Posted
                                    </dt>
                                    <dd>
                                        {note.posted_by_name ?? '—'} ·{' '}
                                        {formatWhen(note.posted_at)}
                                    </dd>
                                </div>
                            </dl>
                            {note.notes ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {note.notes}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                Line items
                            </h2>
                            <div className="divide-y rounded-lg border border-border/80">
                                {note.items.map((item) => (
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
                                                {item.quantity}
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

                        {note.movements.length > 0 && (
                            <section className="space-y-3">
                                <h2 className="text-sm font-medium">
                                    Stock movements
                                </h2>
                                <div className="space-y-2">
                                    {note.movements.map((movement) => (
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
                                                {movement.branch_name}
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
