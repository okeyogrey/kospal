import { Head, Link, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { formatMoney, toMinor } from '@/lib/money';
import {
    close as closeCashSession,
    index as cashSessionsIndex,
    zReport,
} from '@/routes/cash-sessions';
import { store as storeMovement } from '@/routes/cash-sessions/movements';

type Movement = {
    id: number;
    type: string;
    type_label: string;
    amount_formatted: string;
    reason: string | null;
    notes: string | null;
    recorded_by_name: string | null;
    created_at: string | null;
};

type SessionDetail = {
    id: number;
    user_name: string | null;
    user_email: string | null;
    branch_name: string | null;
    status: string;
    status_label: string;
    opened_at: string | null;
    closed_at: string | null;
    opening_float_formatted: string;
    opening_notes: string | null;
    expected_cash_formatted: string | null;
    counted_cash_formatted: string | null;
    closing_float_left_formatted: string | null;
    variance_formatted: string | null;
    variance_reason: string | null;
    variance_approver_name: string | null;
    closed_by_name: string | null;
    has_variance: boolean;
    is_open: boolean;
    movements: Movement[];
};

type Summary = {
    opening_float_minor?: number;
    expected_cash_minor?: number;
    expected_cash_formatted?: string;
    cash_sales_minor?: number;
    paid_ins_minor?: number;
    drops_minor?: number;
    sales?: {
        sale_count?: number;
        total_minor?: number;
    };
    payment_breakdown?: Array<{
        method_label: string;
        total_formatted: string;
        payment_count: number;
    }>;
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

export default function CashSessionShow({
    session,
    summary,
    currency,
    permissions,
}: {
    session: SessionDetail;
    summary: Summary;
    timezone: string;
    currency: string;
    permissions: {
        record_movement: boolean;
        close: boolean;
        force_close: boolean;
    };
}) {
    const movementForm = useForm({
        type: 'paid_in',
        amount: '',
        reason: '',
        notes: '',
    });

    const closeForm = useForm({
        counted_cash: '',
        closing_float_left: '',
        variance_reason: '',
        manager_approval: { pin: '' },
    });

    const expectedMinor = summary.expected_cash_minor ?? 0;
    const countedMinor = toMinor(closeForm.data.counted_cash || '0', currency);
    const previewVariance = countedMinor - expectedMinor;

    return (
        <>
            <Head title={`Cash session · ${session.user_name ?? session.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold">
                                Cash session
                            </h1>
                            <Badge
                                variant={
                                    session.is_open ? 'default' : 'secondary'
                                }
                            >
                                {session.status_label}
                            </Badge>
                            {session.has_variance ? (
                                <Badge variant="destructive">Variance</Badge>
                            ) : null}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {session.user_name} · {session.branch_name} ·
                            Opened {formatDateTime(session.opened_at)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {!session.is_open ? (
                            <Button variant="outline" asChild>
                                <a
                                    href={zReport.url(session.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Z report
                                </a>
                            </Button>
                        ) : null}
                        <Button variant="outline" asChild>
                            <Link href={cashSessionsIndex()}>Back</Link>
                        </Button>
                    </div>
                </div>

                <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                    <h2 className="mb-3 font-medium">Cash summary</h2>
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Opening float
                            </dt>
                            <dd className="font-medium">
                                {session.opening_float_formatted}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Expected cash
                            </dt>
                            <dd className="font-medium">
                                {summary.expected_cash_formatted ??
                                    session.expected_cash_formatted ??
                                    '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Cash sales
                            </dt>
                            <dd className="font-medium">
                                {formatMoney(
                                    summary.cash_sales_minor ?? 0,
                                    currency,
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Sales count
                            </dt>
                            <dd className="font-medium">
                                {summary.sales?.sale_count ?? 0}
                            </dd>
                        </div>
                        {!session.is_open ? (
                            <>
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Counted cash
                                    </dt>
                                    <dd className="font-medium">
                                        {session.counted_cash_formatted ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Closing float
                                    </dt>
                                    <dd className="font-medium">
                                        {session.closing_float_left_formatted ??
                                            '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Variance
                                    </dt>
                                    <dd
                                        className={
                                            session.has_variance
                                                ? 'font-medium text-amber-700 dark:text-amber-300'
                                                : 'font-medium'
                                        }
                                    >
                                        {session.variance_formatted ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Closed by
                                    </dt>
                                    <dd className="font-medium">
                                        {session.closed_by_name ?? '—'}
                                    </dd>
                                </div>
                            </>
                        ) : null}
                    </dl>
                    {session.variance_reason ? (
                        <p className="mt-4 text-sm">
                            <span className="text-muted-foreground">
                                Variance reason:
                            </span>{' '}
                            {session.variance_reason}
                            {session.variance_approver_name
                                ? ` · Approved by ${session.variance_approver_name}`
                                : ''}
                        </p>
                    ) : null}
                </section>

                {permissions.record_movement && session.is_open ? (
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Record movement</h2>
                        <form
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                movementForm.transform((data) => ({
                                    ...data,
                                    amount: toMinor(data.amount || '0', currency),
                                }));
                                movementForm.post(
                                    storeMovement.url(session.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            movementForm.reset(
                                                'amount',
                                                'reason',
                                                'notes',
                                            ),
                                    },
                                );
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="movement_type">Type</Label>
                                <select
                                    id="movement_type"
                                    value={movementForm.data.type}
                                    onChange={(e) =>
                                        movementForm.setData(
                                            'type',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="paid_in">Paid in</option>
                                    <option value="drop">Cash drop</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="movement_amount">Amount</Label>
                                <Input
                                    id="movement_amount"
                                    value={movementForm.data.amount}
                                    onChange={(e) =>
                                        movementForm.setData(
                                            'amount',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={movementForm.errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="movement_reason">Reason</Label>
                                <Input
                                    id="movement_reason"
                                    value={movementForm.data.reason}
                                    onChange={(e) =>
                                        movementForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="movement_notes">Notes</Label>
                                <Input
                                    id="movement_notes"
                                    value={movementForm.data.notes}
                                    onChange={(e) =>
                                        movementForm.setData(
                                            'notes',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex items-end">
                                <Button
                                    type="submit"
                                    disabled={movementForm.processing}
                                >
                                    Record
                                </Button>
                            </div>
                        </form>
                    </section>
                ) : null}

                {permissions.close && session.is_open ? (
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-1 font-medium">
                            Cash reconciliation
                        </h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Count the drawer, enter the totals, and close the
                            session. Expected cash:{' '}
                            {summary.expected_cash_formatted ??
                                formatMoney(expectedMinor, currency)}
                        </p>
                        <form
                            className="grid gap-3 sm:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                closeForm.transform((data) => ({
                                    ...data,
                                    counted_cash: toMinor(
                                        data.counted_cash || '0',
                                        currency,
                                    ),
                                    closing_float_left: toMinor(
                                        data.closing_float_left || '0',
                                        currency,
                                    ),
                                }));
                                closeForm.post(closeCashSession.url(session.id), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="counted_cash">
                                    Counted cash
                                </Label>
                                <Input
                                    id="counted_cash"
                                    value={closeForm.data.counted_cash}
                                    onChange={(e) =>
                                        closeForm.setData(
                                            'counted_cash',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={closeForm.errors.counted_cash}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="closing_float_left">
                                    Closing float left
                                </Label>
                                <Input
                                    id="closing_float_left"
                                    value={closeForm.data.closing_float_left}
                                    onChange={(e) =>
                                        closeForm.setData(
                                            'closing_float_left',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={closeForm.errors.closing_float_left}
                                />
                            </div>
                            {previewVariance !== 0 &&
                            closeForm.data.counted_cash ? (
                                <>
                                    <p className="text-sm text-amber-700 sm:col-span-2 dark:text-amber-300">
                                        Variance preview:{' '}
                                        {formatMoney(
                                            previewVariance,
                                            currency,
                                        )}
                                    </p>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="variance_reason">
                                            Variance reason
                                        </Label>
                                        <Input
                                            id="variance_reason"
                                            value={closeForm.data.variance_reason}
                                            onChange={(e) =>
                                                closeForm.setData(
                                                    'variance_reason',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                closeForm.errors.variance_reason
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="manager_pin">
                                            Manager PIN
                                        </Label>
                                        <Input
                                            id="manager_pin"
                                            type="password"
                                            inputMode="numeric"
                                            value={
                                                closeForm.data.manager_approval
                                                    .pin
                                            }
                                            onChange={(e) =>
                                                closeForm.setData(
                                                    'manager_approval',
                                                    {
                                                        pin: e.target.value,
                                                    },
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                closeForm.errors[
                                                    'manager_approval.pin'
                                                ]
                                            }
                                        />
                                    </div>
                                </>
                            ) : null}
                            <div className="flex items-end sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={closeForm.processing}
                                >
                                    Close drawer & reconcile
                                </Button>
                            </div>
                        </form>
                    </section>
                ) : null}

                <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                    <h2 className="mb-3 font-medium">Movements</h2>
                    {session.movements.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No paid-ins or drops recorded.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border/70">
                            {session.movements.map((movement) => (
                                <li
                                    key={movement.id}
                                    className="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {movement.type_label} ·{' '}
                                            {movement.amount_formatted}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {movement.reason ?? '—'}
                                            {movement.recorded_by_name
                                                ? ` · ${movement.recorded_by_name}`
                                                : ''}
                                            {' · '}
                                            {formatDateTime(movement.created_at)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

CashSessionShow.layout = {
    breadcrumbs: [
        { title: 'Cash management', href: cashSessionsIndex() },
    ],
};
