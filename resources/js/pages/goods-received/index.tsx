import { Head, Link, router } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type NoteRow = {
    id: number;
    reference: string | null;
    status: string;
    supplier_name: string | null;
    branch_name: string | null;
    items_count: number;
    created_by_name: string | null;
    created_at: string | null;
    posted_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    posted: 'default',
    cancelled: 'destructive',
};

export default function GoodsReceivedIndex({
    notes,
    statuses,
    filters,
}: {
    notes: Paginated<NoteRow>;
    statuses: string[];
    filters: {
        search: string;
        status: string | null;
    };
}) {
    return (
        <>
            <Head title="Goods received" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Goods received
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Record stock received from suppliers and post to
                            update inventory.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/goods-received/create">
                            Record goods received
                        </Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/goods-received',
                            {
                                search: String(data.get('search') ?? '') || undefined,
                                status: String(data.get('status') ?? '') || undefined,
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
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {notes.data.length === 0 ? (
                    <EmptyState
                        icon={<PackageCheck className="size-5" />}
                        title="No goods received notes yet"
                        description="Record stock received from a supplier to update inventory."
                    />
                ) : (
                    <div className="space-y-3">
                        {notes.data.map((note) => (
                            <Link
                                key={note.id}
                                href={`/goods-received/${note.id}`}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {note.reference ??
                                                    `#${note.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        note.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {note.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {note.supplier_name} ·{' '}
                                            {note.branch_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {note.items_count} item
                                            {note.items_count === 1
                                                ? ''
                                                : 's'}
                                            {note.created_by_name
                                                ? ` · ${note.created_by_name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-xs text-muted-foreground sm:text-right">
                                        {note.created_at
                                            ? new Date(
                                                  note.created_at,
                                              ).toLocaleString()
                                            : null}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={notes.links} />
                    </div>
                )}
            </div>
        </>
    );
}
