import { Head, router } from '@inertiajs/react';
import { Package, Warehouse } from 'lucide-react';
import { MetricCard } from '@/components/analytics/metric-card';
import { EmptyState } from '@/components/states';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type Option = { id: number; name: string; sku?: string };

type BranchValue = {
    branch_id: number;
    branch_name: string;
    quantity: number;
    value: number;
};

type ProductValue = {
    product_id: number;
    product_name: string;
    sku: string | null;
    cost_price: number;
    quantity: number;
    value: number;
};

type Summary = {
    total_value: number;
    total_quantity: number;
    product_count: number;
    by_branch: BranchValue[];
    products: ProductValue[];
};

type CostHistoryRow = {
    id: number;
    product_id: number;
    product_name: string | null;
    previous_cost: number;
    new_cost: number;
    quantity_on_hand: number;
    quantity_received: number | null;
    received_unit_cost: number | null;
    source: string | null;
    user_name: string | null;
    created_at: string | null;
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function InventoryValuation({
    summary,
    costHistory,
    branches,
    products,
    filters,
    currency,
}: {
    summary: Summary;
    costHistory: CostHistoryRow[];
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
            <Head title="Stock valuation" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Stock valuation
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Inventory value at cost, by branch and product.
                        </p>
                    </div>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/inventory/valuation',
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
                        <option value="">All products (cost history)</option>
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

                <div className="grid gap-3 sm:grid-cols-3">
                    <MetricCard
                        label="Total stock value"
                        value={formatMoney(summary.total_value, currency)}
                        icon={<Warehouse className="size-4" />}
                    />
                    <MetricCard
                        label="Total quantity"
                        value={summary.total_quantity.toLocaleString()}
                        icon={<Package className="size-4" />}
                    />
                    <MetricCard
                        label="Products valued"
                        value={summary.product_count.toLocaleString()}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_1fr]">
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">By branch</h2>
                        {summary.by_branch.length === 0 ? (
                            <EmptyState
                                title="No stock valued"
                                description="Receive stock to see valuation by branch."
                            />
                        ) : (
                            <div className="divide-y rounded-lg border border-border/80">
                                {summary.by_branch.map((row) => (
                                    <div
                                        key={row.branch_id}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <span className="font-medium">
                                            {row.branch_name}
                                        </span>
                                        <div className="text-right">
                                            <p className="font-medium tabular-nums">
                                                {formatMoney(
                                                    row.value,
                                                    currency,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {row.quantity} units
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">
                            Top products by value
                        </h2>
                        {summary.products.length === 0 ? (
                            <EmptyState
                                title="No stock valued"
                                description="Receive stock to see valuation by product."
                            />
                        ) : (
                            <div className="max-h-[26rem] divide-y overflow-y-auto rounded-lg border border-border/80">
                                {summary.products.map((row) => (
                                    <div
                                        key={row.product_id}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {row.product_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.sku} ·{' '}
                                                {row.quantity} units ·{' '}
                                                {formatMoney(
                                                    row.cost_price,
                                                    currency,
                                                )}{' '}
                                                each
                                            </p>
                                        </div>
                                        <span className="font-medium tabular-nums">
                                            {formatMoney(row.value, currency)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Cost history</h2>
                    {costHistory.length === 0 ? (
                        <EmptyState
                            title="No cost changes"
                            description="Cost updates from receiving stock will appear here."
                        />
                    ) : (
                        <div className="divide-y rounded-lg border border-border/80">
                            {costHistory.map((row) => (
                                <div
                                    key={row.id}
                                    className="flex flex-col gap-1 px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {row.product_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatMoney(
                                                row.previous_cost,
                                                currency,
                                            )}{' '}
                                            →{' '}
                                            {formatMoney(
                                                row.new_cost,
                                                currency,
                                            )}
                                            {row.source
                                                ? ` · ${row.source}`
                                                : ''}
                                        </p>
                                    </div>
                                    <p className="text-xs text-muted-foreground sm:text-right">
                                        {row.user_name ?? 'System'} ·{' '}
                                        {formatWhen(row.created_at)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
