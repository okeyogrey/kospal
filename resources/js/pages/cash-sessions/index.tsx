import { Head, Link, router } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as cashSessionsIndex, show as cashSessionsShow } from '@/routes/cash-sessions';

type SessionRow = {
    id: number;
    user_name: string | null;
    branch_name: string | null;
    status: string;
    status_label: string;
    opened_at: string | null;
    closed_at: string | null;
    opening_float_formatted: string;
    expected_cash_formatted: string | null;
    counted_cash_formatted: string | null;
    variance_formatted: string | null;
    has_variance: boolean;
};

type Option = { id: number; name: string | null; email?: string | null };
type StatusOption = { value: string; label: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleString();
}

export default function CashSessionsIndex({
    sessions,
    branches,
    staff,
    statuses,
    filters,
    timezone,
}: {
    sessions: Paginated<SessionRow>;
    branches: Option[];
    staff: Option[];
    statuses: StatusOption[];
    filters: {
        branch_id: number | null;
        user_id: number | null;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
        has_variance: string | null;
    };
    timezone: string;
}) {
    return (
        <>
            <Head title="Cash management" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Cash management
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Opening floats, reconciliations, and variances ({timezone}).
                    </p>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-7"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            cashSessionsIndex.url({
                                query: {
                                    branch_id:
                                        String(data.get('branch_id') ?? '') ||
                                        undefined,
                                    user_id:
                                        String(data.get('user_id') ?? '') ||
                                        undefined,
                                    status:
                                        String(data.get('status') ?? '') ||
                                        undefined,
                                    date_from:
                                        String(data.get('date_from') ?? '') ||
                                        undefined,
                                    date_to:
                                        String(data.get('date_to') ?? '') ||
                                        undefined,
                                    has_variance:
                                        String(data.get('has_variance') ?? '') ||
                                        undefined,
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
                    <select
                        name="user_id"
                        defaultValue={filters.user_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All cashiers</option>
                        {staff.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="has_variance"
                        defaultValue={filters.has_variance ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All variances</option>
                        <option value="1">With variance</option>
                        <option value="0">Balanced</option>
                    </select>
                    <Input
                        type="date"
                        name="date_from"
                        defaultValue={filters.date_from ?? ''}
                    />
                    <Input
                        type="date"
                        name="date_to"
                        defaultValue={filters.date_to ?? ''}
                    />
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                <div className="rounded-2xl border border-border/80 bg-card/80">
                    {sessions.data.length === 0 ? (
                        <EmptyState
                            title="No cash sessions yet"
                            description="Cash drawer activity will appear here."
                            icon={<Banknote className="size-5" />}
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-border/70">
                                {sessions.data.map((session) => (
                                    <li
                                        key={session.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={cashSessionsShow(
                                                        session.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {session.user_name}
                                                </Link>
                                                <Badge
                                                    variant={
                                                        session.status ===
                                                        'open'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {session.status_label}
                                                </Badge>
                                                {session.has_variance ? (
                                                    <Badge variant="destructive">
                                                        Variance
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {session.branch_name} · Open{' '}
                                                {formatDateTime(
                                                    session.opened_at,
                                                )}
                                                {session.closed_at
                                                    ? ` · Closed ${formatDateTime(session.closed_at)}`
                                                    : ''}
                                                {' · Float '}
                                                {session.opening_float_formatted}
                                                {session.variance_formatted
                                                    ? ` · Variance ${session.variance_formatted}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={cashSessionsShow(
                                                    session.id,
                                                )}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <Pagination links={sessions.links} />
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

CashSessionsIndex.layout = {
    breadcrumbs: [{ title: 'Cash management', href: cashSessionsIndex() }],
};
