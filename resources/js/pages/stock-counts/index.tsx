import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type CountRow = {
    id: number;
    reference: string | null;
    status: string;
    branch_name: string | null;
    items_count: number;
    created_by_name: string | null;
    created_at: string | null;
    completed_at: string | null;
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
    in_progress: 'default',
    completed: 'outline',
    cancelled: 'destructive',
};

export default function StockCountsIndex({
    counts,
    branches,
    statuses,
    filters,
}: {
    counts: Paginated<CountRow>;
    branches: Option[];
    statuses: string[];
    filters: {
        status: string | null;
        branch_id: number | null;
    };
}) {
    return (
        <>
            <Head title="Stock counts" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Stock counts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Reconcile physical stock against system quantities.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/stock-counts/create">
                            Start stock count
                        </Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/stock-counts',
                            {
                                status: String(data.get('status') ?? '') || undefined,
                                branch_id:
                                    String(data.get('branch_id') ?? '') ||
                                    undefined,
                            },
                            { preserveState: true },
                        );
                    }}
                >
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

                {counts.data.length === 0 ? (
                    <EmptyState
                        icon={<ClipboardCheck className="size-5" />}
                        title="No stock counts yet"
                        description="Start a stock count to reconcile physical inventory."
                    />
                ) : (
                    <div className="space-y-3">
                        {counts.data.map((count) => (
                            <Link
                                key={count.id}
                                href={`/stock-counts/${count.id}`}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {count.reference ??
                                                    `#${count.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        count.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {count.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {count.branch_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {count.items_count} item
                                            {count.items_count === 1
                                                ? ''
                                                : 's'}
                                            {count.created_by_name
                                                ? ` · ${count.created_by_name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-xs text-muted-foreground sm:text-right">
                                        {count.created_at
                                            ? new Date(
                                                  count.created_at,
                                              ).toLocaleString()
                                            : null}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={counts.links} />
                    </div>
                )}
            </div>
        </>
    );
}
