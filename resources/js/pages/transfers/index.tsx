import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create as createTransfer,
    index as transfersIndex,
    show as showTransfer,
} from '@/routes/stock-transfers';

type TransferRow = {
    id: number;
    reference: string | null;
    status: string;
    source_branch_name: string | null;
    destination_branch_name: string | null;
    items_count: number;
    created_by_name: string | null;
    created_at: string | null;
    dispatched_at: string | null;
    received_at: string | null;
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
    dispatched: 'default',
    received: 'outline',
    cancelled: 'destructive',
};

export default function TransfersIndex({
    transfers,
    branches,
    statuses,
    filters,
}: {
    transfers: Paginated<TransferRow>;
    branches: Option[];
    statuses: string[];
    filters: {
        search: string;
        status: string | null;
        source_branch_id: number | null;
        destination_branch_id: number | null;
    };
}) {
    return (
        <>
            <Head title="Stock transfers" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Stock transfers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Move stock between branches with dispatch and
                            receive controls.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={createTransfer()}>Create transfer</Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            transfersIndex.url({
                                query: {
                                    search:
                                        String(data.get('search') ?? '') ||
                                        undefined,
                                    status:
                                        String(data.get('status') ?? '') ||
                                        undefined,
                                    source_branch_id:
                                        String(
                                            data.get('source_branch_id') ?? '',
                                        ) || undefined,
                                    destination_branch_id:
                                        String(
                                            data.get(
                                                'destination_branch_id',
                                            ) ?? '',
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
                                {status}
                            </option>
                        ))}
                    </select>
                    <select
                        name="source_branch_id"
                        defaultValue={filters.source_branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Any source</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                From: {branch.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="destination_branch_id"
                        defaultValue={filters.destination_branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Any destination</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                To: {branch.name}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {transfers.data.length === 0 ? (
                    <EmptyState
                        icon={<ArrowLeftRight className="size-5" />}
                        title="No transfers yet"
                        description="Create a draft to move products between branches."
                    />
                ) : (
                    <div className="space-y-3">
                        {transfers.data.map((transfer) => (
                            <Link
                                key={transfer.id}
                                href={showTransfer(transfer)}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {transfer.reference ??
                                                    `#${transfer.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        transfer.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {transfer.status}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {transfer.source_branch_name} →{' '}
                                            {transfer.destination_branch_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {transfer.items_count} item
                                            {transfer.items_count === 1
                                                ? ''
                                                : 's'}
                                            {transfer.created_by_name
                                                ? ` · ${transfer.created_by_name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-xs text-muted-foreground sm:text-right">
                                        {transfer.created_at
                                            ? new Date(
                                                  transfer.created_at,
                                              ).toLocaleString()
                                            : null}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={transfers.links} />
                    </div>
                )}
            </div>
        </>
    );
}
