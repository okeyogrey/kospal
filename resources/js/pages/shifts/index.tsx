import { Head, Link, router } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as shiftsIndex, show as shiftsShow } from '@/routes/shifts';

type ShiftRow = {
    id: number;
    user_name: string | null;
    user_email: string | null;
    branch_name: string | null;
    role_at_clock_in: string;
    status: string;
    status_label: string;
    clocked_in_at: string | null;
    clocked_out_at: string | null;
    duration_label: string | null;
    is_open: boolean;
};

type Option = { id: number; name: string | null; email?: string | null; role?: string };
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

export default function ShiftsIndex({
    shifts,
    branches,
    staff,
    statuses,
    filters,
    timezone,
}: {
    shifts: Paginated<ShiftRow>;
    branches: Option[];
    staff: Option[];
    statuses: StatusOption[];
    filters: {
        branch_id: number | null;
        user_id: number | null;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
    };
    timezone: string;
}) {
    return (
        <>
            <Head title="Shifts" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Shifts
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor staff clock-in and clock-out times ({timezone}).
                    </p>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            shiftsIndex.url({
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
                        <option value="">All staff</option>
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
                    {shifts.data.length === 0 ? (
                        <EmptyState
                            title="No shifts yet"
                            description="Clock-in activity will appear here."
                            icon={<Clock className="size-5" />}
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-border/70">
                                {shifts.data.map((shift) => (
                                    <li
                                        key={shift.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={shiftsShow(shift.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {shift.user_name}
                                                </Link>
                                                <Badge
                                                    variant={
                                                        shift.is_open
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {shift.status_label}
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {shift.branch_name} ·{' '}
                                                {shift.role_at_clock_in} · In{' '}
                                                {formatDateTime(
                                                    shift.clocked_in_at,
                                                )}
                                                {shift.clocked_out_at
                                                    ? ` · Out ${formatDateTime(shift.clocked_out_at)}`
                                                    : ''}
                                                {shift.duration_label
                                                    ? ` · ${shift.duration_label}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={shiftsShow(shift.id)}>
                                                Open
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <Pagination links={shifts.links} />
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

ShiftsIndex.layout = {
    breadcrumbs: [{ title: 'Shifts', href: shiftsIndex() }],
};
