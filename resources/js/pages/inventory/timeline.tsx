import { Head, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type Option = { id: number; name: string; sku?: string };

type TimelineEntry = {
    id: string;
    kind: string;
    action: string;
    product_id: number | null;
    product_name: string | null;
    branch_id: number | null;
    branch_name: string | null;
    quantity_delta: number | null;
    unit_cost: number | null;
    user_name: string | null;
    note: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function InventoryTimeline({
    entries,
    branches,
    products,
    filters,
    currency,
}: {
    entries: TimelineEntry[];
    branches: Option[];
    products: Option[];
    filters: {
        branch_id: number | null;
        product_id: number | null;
    };
    currency: string;
}) {
    return (
        <>
            <Head title="Inventory timeline" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Inventory timeline
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            A combined feed of stock movements and inventory
                            activity.
                        </p>
                    </div>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/inventory/timeline',
                            {
                                branch_id:
                                    String(data.get('branch_id') ?? '') ||
                                    undefined,
                                product_id:
                                    String(data.get('product_id') ?? '') ||
                                    undefined,
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <select
                        name="branch_id"
                        defaultValue={filters.branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All branches</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="product_id"
                        defaultValue={filters.product_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All products</option>
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                                {product.sku ? ` (${product.sku})` : ''}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {entries.length === 0 ? (
                    <EmptyState
                        icon={<History className="size-5" />}
                        title="No activity yet"
                        description="Stock movements and inventory events will appear here."
                    />
                ) : (
                    <ol className="space-y-3 border-l border-border pl-4">
                        {entries.map((entry) => (
                            <li key={entry.id} className="relative">
                                <span className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-foreground" />
                                <div className="rounded-lg border border-border/80 px-3 py-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-medium">
                                                {entry.action.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                            <Badge variant="outline">
                                                {entry.kind}
                                            </Badge>
                                        </div>
                                        {entry.quantity_delta !== null && (
                                            <span
                                                className={
                                                    'text-sm font-medium tabular-nums ' +
                                                    (entry.quantity_delta > 0
                                                        ? 'text-emerald-600'
                                                        : entry.quantity_delta <
                                                            0
                                                          ? 'text-destructive'
                                                          : '')
                                                }
                                            >
                                                {entry.quantity_delta > 0
                                                    ? '+'
                                                    : ''}
                                                {entry.quantity_delta}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {entry.product_name}
                                        {entry.branch_name
                                            ? ` · ${entry.branch_name}`
                                            : ''}
                                        {entry.unit_cost != null
                                            ? ` · ${formatMoney(entry.unit_cost, currency)} each`
                                            : ''}
                                    </p>
                                    {entry.note ? (
                                        <p className="text-xs text-muted-foreground">
                                            {entry.note}
                                        </p>
                                    ) : null}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {entry.user_name ?? 'System'} ·{' '}
                                        {formatWhen(entry.created_at)}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </>
    );
}
