import { Head, Link, router } from '@inertiajs/react';
import { ShoppingBasket } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type OrderRow = {
    id: number;
    reference: string | null;
    status: string;
    supplier_name: string | null;
    branch_name: string | null;
    items_count: number;
    expected_at: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

type Option = { id: number; name: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
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

export default function PurchaseOrdersIndex({
    orders,
    suppliers,
    branches,
    statuses,
    filters,
}: {
    orders: Paginated<OrderRow>;
    suppliers: Option[];
    branches: Option[];
    statuses: string[];
    filters: {
        search: string;
        status: string | null;
        supplier_id: number | null;
        branch_id: number | null;
    };
}) {
    return (
        <>
            <Head title="Purchase orders" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Purchase orders
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Order stock from suppliers and track fulfillment.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/purchase-orders/create">
                            Create purchase order
                        </Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/purchase-orders',
                            {
                                search: String(data.get('search') ?? '') || undefined,
                                status: String(data.get('status') ?? '') || undefined,
                                supplier_id:
                                    String(data.get('supplier_id') ?? '') ||
                                    undefined,
                                branch_id:
                                    String(data.get('branch_id') ?? '') ||
                                    undefined,
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="search"
                        placeholder="Search reference…"
                        defaultValue={filters.search}
                    />
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status.replaceAll('_', ' ')}
                            </option>
                        ))}
                    </select>
                    <select
                        name="supplier_id"
                        defaultValue={filters.supplier_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Any supplier</option>
                        {suppliers.map((supplier) => (
                            <option key={supplier.id} value={supplier.id}>
                                {supplier.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="branch_id"
                        defaultValue={filters.branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Any branch</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={<ShoppingBasket className="size-5" />}
                        title="No purchase orders yet"
                        description="Create a purchase order to start ordering stock from a supplier."
                    />
                ) : (
                    <div className="space-y-3">
                        {orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={`/purchase-orders/${order.id}`}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {order.reference ??
                                                    `#${order.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        order.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {order.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {order.supplier_name} ·{' '}
                                            {order.branch_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {order.items_count} item
                                            {order.items_count === 1
                                                ? ''
                                                : 's'}
                                            {order.created_by_name
                                                ? ` · ${order.created_by_name}`
                                                : ''}
                                            {order.expected_at
                                                ? ` · Expected ${order.expected_at}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-xs text-muted-foreground sm:text-right">
                                        {order.created_at
                                            ? new Date(
                                                  order.created_at,
                                              ).toLocaleString()
                                            : null}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={orders.links} />
                    </div>
                )}
            </div>
        </>
    );
}
