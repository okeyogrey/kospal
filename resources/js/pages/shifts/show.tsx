import { Head, Link, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import {
    forceClose,
    index as shiftsIndex,
} from '@/routes/shifts';

type ShiftDetail = {
    id: number;
    user_name: string | null;
    user_email: string | null;
    branch_name: string | null;
    role_at_clock_in: string;
    status: string;
    status_label: string;
    clocked_in_at: string | null;
    clocked_out_at: string | null;
    clocked_out_by_name: string | null;
    close_reason: string | null;
    notes: string | null;
    duration_label: string | null;
    is_open: boolean;
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

export default function ShiftShow({
    shift,
    timezone,
    permissions,
}: {
    shift: ShiftDetail;
    timezone: string;
    permissions: { force_close: boolean };
}) {
    const form = useForm({
        close_reason: '',
    });

    return (
        <>
            <Head title={`Shift · ${shift.user_name ?? shift.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                {shift.user_name}
                            </h1>
                            <Badge
                                variant={
                                    shift.is_open ? 'default' : 'secondary'
                                }
                            >
                                {shift.status_label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {shift.user_email} · {shift.branch_name} ·{' '}
                            {shift.role_at_clock_in} · {timezone}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={shiftsIndex()}>Back to shifts</Link>
                    </Button>
                </div>

                <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Clocked in
                            </dt>
                            <dd className="font-medium">
                                {formatDateTime(shift.clocked_in_at)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Clocked out
                            </dt>
                            <dd className="font-medium">
                                {formatDateTime(shift.clocked_out_at)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Duration
                            </dt>
                            <dd className="font-medium">
                                {shift.duration_label ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Closed by
                            </dt>
                            <dd className="font-medium">
                                {shift.clocked_out_by_name ?? '—'}
                            </dd>
                        </div>
                        {shift.close_reason ? (
                            <div className="sm:col-span-2">
                                <dt className="text-sm text-muted-foreground">
                                    Close reason
                                </dt>
                                <dd className="font-medium">
                                    {shift.close_reason}
                                </dd>
                            </div>
                        ) : null}
                        {shift.notes ? (
                            <div className="sm:col-span-2">
                                <dt className="text-sm text-muted-foreground">
                                    Notes
                                </dt>
                                <dd className="font-medium">{shift.notes}</dd>
                            </div>
                        ) : null}
                    </dl>
                </section>

                {permissions.force_close && shift.is_open ? (
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Force close shift</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Use this if the staff member forgot to clock out.
                        </p>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(forceClose.url(shift.id), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="close_reason">Reason</Label>
                                <Input
                                    id="close_reason"
                                    value={form.data.close_reason}
                                    onChange={(e) =>
                                        form.setData(
                                            'close_reason',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.close_reason}
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Force close
                            </Button>
                        </form>
                    </section>
                ) : null}
            </div>
        </>
    );
}

ShiftShow.layout = {
    breadcrumbs: [{ title: 'Shifts', href: shiftsIndex() }],
};
