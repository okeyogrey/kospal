import { Head, Link, router, useForm } from '@inertiajs/react';
import { PackageCheck, PackageX, TriangleAlert, Warehouse } from 'lucide-react';
import { AnalyticsPieChart } from '@/components/analytics/pie-chart';
import { MetricCard } from '@/components/analytics/metric-card';
import InputError from '@/components/input-error';
import { ProductSearchSelect } from '@/components/product-search-select';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as inventoryIndex,
    lowStock,
} from '@/routes/inventory';
import { store as storeAdjustment } from '@/routes/inventory/adjustments';
import { store as storeReceiveStock } from '@/routes/inventory/receive-stock';
import { show as productsShow } from '@/routes/products';

type BalanceRow = {
    id: number;
    branch_id: number;
    branch_name: string | null;
    product_id: number;
    product_name: string | null;
    sku: string | null;
    quantity: number;
    reorder_level: number;
    is_low_stock: boolean;
    value_formatted: string;
};

type Option = { id: number; name: string; sku?: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

type AdjustmentReason = {
    value: string;
    label: string;
};

function stockHealthPercent(quantity: number, reorderLevel: number): number {
    if (quantity <= 0) {
        return 0;
    }

    const target = Math.max(reorderLevel * 2, reorderLevel + 1, 1);

    return Math.min(100, Math.round((quantity / target) * 100));
}

export default function InventoryIndex({
    balances,
    branches,
    products,
    filters,
    summary,
    adjustmentReasons,
    currency,
}: {
    balances: Paginated<BalanceRow>;
    branches: Option[];
    products: Option[];
    filters: {
        search: string;
        branch_id: number | null;
        status: string | null;
    };
    summary: {
        total_value_minor: number;
        total_value_formatted: string;
        in_stock_count: number;
        low_stock_count: number;
        out_of_stock_count: number;
    };
    adjustmentReasons: AdjustmentReason[];
    currency: string;
}) {
    const receiveForm = useForm({
        branch_id: String(filters.branch_id ?? branches[0]?.id ?? ''),
        product_id: '',
        quantity: '',
        note: '',
    });

    const adjustForm = useForm({
        branch_id: String(filters.branch_id ?? branches[0]?.id ?? ''),
        product_id: '',
        quantity: '',
        reason: 'loss',
        note: '',
    });

    const statusChart = [
        { label: 'In stock', value: summary.in_stock_count },
        { label: 'Low stock', value: summary.low_stock_count },
        { label: 'Out of stock', value: summary.out_of_stock_count },
    ].filter((point) => point.value > 0);

    return (
        <>
            <Head title="Inventory" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Inventory
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Branch stock valued at cost in {currency}.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={lowStock()}>Low stock alerts</Link>
                        </Button>
                        <form
                            className="flex flex-wrap gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.get(
                                    inventoryIndex.url({
                                        query: {
                                            search: String(
                                                data.get('search') ?? '',
                                            ),
                                            branch_id:
                                                String(
                                                    data.get('branch_id') ??
                                                        '',
                                                ) || undefined,
                                            status:
                                                String(
                                                    data.get('status') ?? '',
                                                ) || undefined,
                                        },
                                    }),
                                    {},
                                    { preserveState: true },
                                );
                            }}
                        >
                            <Input
                                name="search"
                                placeholder="Search products"
                                defaultValue={filters.search}
                                className="w-40 sm:w-52"
                            />
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
                                name="status"
                                defaultValue={filters.status ?? ''}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All stock</option>
                                <option value="low_stock">Low stock</option>
                                <option value="in_stock">In stock</option>
                                <option value="out_of_stock">
                                    Out of stock
                                </option>
                            </select>
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Total stock value"
                        value={summary.total_value_formatted}
                        icon={<Warehouse className="size-4" />}
                    />
                    <MetricCard
                        label="In stock"
                        value={summary.in_stock_count}
                        icon={<PackageCheck className="size-4" />}
                        accent="accent"
                    />
                    <MetricCard
                        label="Low stock"
                        value={summary.low_stock_count}
                        icon={<TriangleAlert className="size-4" />}
                        accent={
                            summary.low_stock_count > 0 ? 'warning' : 'muted'
                        }
                    />
                    <MetricCard
                        label="Out of stock"
                        value={summary.out_of_stock_count}
                        icon={<PackageX className="size-4" />}
                        accent={
                            summary.out_of_stock_count > 0
                                ? 'warning'
                                : 'muted'
                        }
                    />
                </div>

                {statusChart.length > 0 ? (
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm sm:max-w-xl">
                        <h2 className="mb-2 font-display text-lg font-semibold">
                            Stock health
                        </h2>
                        <p className="mb-2 text-sm text-muted-foreground">
                            Distribution of SKUs by stock status.
                        </p>
                        <AnalyticsPieChart data={statusChart} />
                    </section>
                ) : null}

                <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {balances.data.length === 0 ? (
                            <EmptyState
                                title="No inventory balances"
                                description="Receive stock to start tracking quantities."
                                icon={<Warehouse className="size-5" />}
                            />
                        ) : (
                            <>
                                <ul className="divide-y divide-border/70">
                                    {balances.data.map((row) => {
                                        const health = stockHealthPercent(
                                            row.quantity,
                                            row.reorder_level,
                                        );

                                        return (
                                            <li
                                                key={row.id}
                                                className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Link
                                                            href={productsShow(
                                                                row.product_id,
                                                            )}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {row.product_name}
                                                        </Link>
                                                        {row.is_low_stock && (
                                                            <Badge variant="secondary">
                                                                Low stock
                                                            </Badge>
                                                        )}
                                                        {row.quantity === 0 && (
                                                            <Badge variant="outline">
                                                                Out of stock
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {row.sku} ·{' '}
                                                        {row.branch_name} ·
                                                        Value{' '}
                                                        {row.value_formatted} ·
                                                        Reorder{' '}
                                                        {row.reorder_level}
                                                    </p>
                                                    <div className="mt-2 h-1.5 max-w-xs overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className={
                                                                row.quantity ===
                                                                0
                                                                    ? 'h-full w-0 rounded-full bg-destructive/70'
                                                                    : row.is_low_stock
                                                                      ? 'h-full rounded-full bg-amber-500/80'
                                                                      : 'h-full rounded-full bg-primary/80'
                                                            }
                                                            style={{
                                                                width: `${Math.max(row.quantity === 0 ? 0 : 6, health)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                                <p className="text-lg font-semibold tabular-nums">
                                                    {row.quantity}
                                                </p>
                                            </li>
                                        );
                                    })}
                                </ul>
                                <Pagination links={balances.links} />
                            </>
                        )}
                    </div>

                    <div className="space-y-6">
                        <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <h2 className="mb-3 font-medium">Receive stock</h2>
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    receiveForm.post(storeReceiveStock.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            receiveForm.reset(
                                                'quantity',
                                                'note',
                                            ),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="receive_branch">
                                        Branch
                                    </Label>
                                    <select
                                        id="receive_branch"
                                        value={receiveForm.data.branch_id}
                                        onChange={(e) =>
                                            receiveForm.setData(
                                                'branch_id',
                                                e.target.value,
                                            )
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        required
                                    >
                                        {branches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <ProductSearchSelect
                                    id="receive_product"
                                    products={products}
                                    value={receiveForm.data.product_id}
                                    onChange={(value) =>
                                        receiveForm.setData('product_id', value)
                                    }
                                    error={receiveForm.errors.product_id}
                                    required
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="receive_quantity">
                                        Quantity
                                    </Label>
                                    <Input
                                        id="receive_quantity"
                                        type="number"
                                        min={1}
                                        value={receiveForm.data.quantity}
                                        onChange={(e) =>
                                            receiveForm.setData(
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={receiveForm.errors.quantity}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="receive_note">Note</Label>
                                    <Input
                                        id="receive_note"
                                        value={receiveForm.data.note}
                                        onChange={(e) =>
                                            receiveForm.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={receiveForm.processing}
                                >
                                    Record stock received
                                </Button>
                            </form>
                        </section>

                        <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                            <h2 className="mb-3 font-medium">Record loss</h2>
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    adjustForm.post(storeAdjustment.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            adjustForm.reset(
                                                'quantity',
                                                'note',
                                            ),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="adjust_branch">
                                        Branch
                                    </Label>
                                    <select
                                        id="adjust_branch"
                                        value={adjustForm.data.branch_id}
                                        onChange={(e) =>
                                            adjustForm.setData(
                                                'branch_id',
                                                e.target.value,
                                            )
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        required
                                    >
                                        {branches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <ProductSearchSelect
                                    id="adjust_product"
                                    products={products}
                                    value={adjustForm.data.product_id}
                                    onChange={(value) =>
                                        adjustForm.setData('product_id', value)
                                    }
                                    error={adjustForm.errors.product_id}
                                    required
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="adjust_quantity">
                                        Quantity lost
                                    </Label>
                                    <Input
                                        id="adjust_quantity"
                                        type="number"
                                        min={1}
                                        value={adjustForm.data.quantity}
                                        onChange={(e) =>
                                            adjustForm.setData(
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={adjustForm.errors.quantity}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <select
                                        id="reason"
                                        value={adjustForm.data.reason}
                                        onChange={(e) =>
                                            adjustForm.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        required
                                    >
                                        {adjustmentReasons.map((reason) => (
                                            <option
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={adjustForm.errors.reason}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="adjust_note">Note</Label>
                                    <Input
                                        id="adjust_note"
                                        value={adjustForm.data.note}
                                        onChange={(e) =>
                                            adjustForm.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={adjustForm.errors.note}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={adjustForm.processing}
                                >
                                    Record loss
                                </Button>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: inventoryIndex() }],
};
