import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as inventoryIndex, lowStock } from '@/routes/inventory';
import { show as productsShow } from '@/routes/products';

type SupplierContact = {
    id: number;
    name: string;
    phone: string | null;
    contact_name: string | null;
};

type AlertRow = {
    id: number;
    product_id: number;
    product_name: string | null;
    sku: string | null;
    branch_name: string | null;
    quantity: number;
    reorder_level: number;
    value_formatted: string;
    suppliers: SupplierContact[];
};

type Option = { id: number; name: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function InventoryLowStock({
    alerts,
    branches,
    filters,
}: {
    alerts: Paginated<AlertRow>;
    branches: Option[];
    filters: { branch_id: number | null };
    currency: string;
}) {
    return (
        <>
            <Head title="Low stock alerts" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Low stock alerts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Products at or below their reorder level.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={inventoryIndex()}>Back to inventory</Link>
                        </Button>
                        <form
                            className="flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.get(
                                    lowStock.url({
                                        query: {
                                            branch_id:
                                                String(
                                                    data.get('branch_id') ??
                                                        '',
                                                ) || undefined,
                                        },
                                    }),
                                    {},
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
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>
                    </div>
                </div>

                <div className="rounded-2xl border border-border/80 bg-card/80">
                    {alerts.data.length === 0 ? (
                        <EmptyState
                            title="No low-stock items"
                            description="All accessible branch stock is above reorder levels."
                            icon={<AlertTriangle className="size-5" />}
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-border/70">
                                {alerts.data.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={productsShow(
                                                        row.product_id,
                                                    )}
                                                    className="font-display text-lg font-semibold hover:underline"
                                                >
                                                    {row.product_name}
                                                </Link>
                                                <Badge variant="secondary">
                                                    Low stock
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {row.sku} · {row.branch_name} ·
                                                Reorder {row.reorder_level} ·
                                                Value {row.value_formatted}
                                            </p>
                                            {row.suppliers.length > 0 ? (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    Suppliers:{' '}
                                                    {row.suppliers
                                                        .map(
                                                            (supplier) =>
                                                                supplier.name,
                                                        )
                                                        .join(', ')}
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="flex flex-col items-start gap-3 sm:items-end">
                                            <p className="text-lg font-semibold tabular-nums">
                                                {row.quantity}
                                            </p>
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={productsShow(
                                                            row.product_id,
                                                        )}
                                                    >
                                                        View product
                                                    </Link>
                                                </Button>
                                                {row.suppliers
                                                    .filter(
                                                        (supplier) =>
                                                            supplier.phone,
                                                    )
                                                    .map((supplier) => (
                                                        <Button
                                                            key={supplier.id}
                                                            size="sm"
                                                            variant="secondary"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`tel:${supplier.phone}`}
                                                            >
                                                                Call{' '}
                                                                {supplier.name}
                                                            </a>
                                                        </Button>
                                                    ))}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Pagination links={alerts.links} />
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

InventoryLowStock.layout = {
    breadcrumbs: [
        { title: 'Inventory', href: inventoryIndex() },
        { title: 'Low stock', href: lowStock() },
    ],
};
