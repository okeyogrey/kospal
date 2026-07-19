import { Head, Link, router, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type RequestRow = {
    id: number;
    status: string;
    requested_plan: string;
    current_plan: string;
    transaction_code: string;
    notes: string | null;
    reviewer_notes: string | null;
    created_at: string | null;
    reviewed_at: string | null;
    business: {
        id: number;
        name: string;
        plan: string;
        subscription_status: string;
        subscription_ends_at: string | null;
    };
    requested_by: {
        name: string | null;
        email: string | null;
    };
    reviewed_by: string | null;
};

type BusinessRow = {
    id: number;
    name: string;
    plan: string;
    subscription_status: string;
    subscription_ends_at: string | null;
    owner: { name: string; email: string } | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

export default function PlatformSubscriptionRequestsIndex({
    requests,
    businesses,
    filters,
    plans,
    subscription_statuses,
    default_subscription_days,
}: {
    requests: Paginated<RequestRow>;
    businesses: BusinessRow[];
    filters: { status: string };
    plans: string[];
    subscription_statuses: string[];
    default_subscription_days: number;
}) {
    const endsDefault = () => {
        const date = new Date();
        date.setDate(date.getDate() + default_subscription_days);

        return date.toISOString().slice(0, 10);
    };

    return (
        <>
            <Head title="Subscription requests" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Subscription review
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Approve offline payments, update plans, and audit
                            every change.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/platform/payment-instructions">
                            Payment instructions
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {['pending', 'approved', 'rejected', 'all'].map(
                        (status) => (
                            <Button
                                key={status}
                                type="button"
                                size="sm"
                                variant={
                                    filters.status === status
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    router.get(
                                        '/platform/subscription-requests',
                                        { status },
                                        { preserveState: true },
                                    )
                                }
                            >
                                {status}
                            </Button>
                        ),
                    )}
                </div>

                <section className="space-y-4">
                    {requests.data.length === 0 ? (
                        <div className="rounded-2xl border border-border/80 bg-card/80 p-6 text-sm text-muted-foreground">
                            No subscription requests for this filter.
                        </div>
                    ) : (
                        requests.data.map((item) => (
                            <RequestCard
                                key={item.id}
                                item={item}
                                plans={plans}
                                subscriptionStatuses={subscription_statuses}
                                endsDefault={endsDefault()}
                            />
                        ))
                    )}
                </section>

                <section className="rounded-2xl border border-border/80 bg-card/80">
                    <div className="border-b border-border/70 px-4 py-3 font-medium">
                        Business subscriptions
                    </div>
                    <ul className="divide-y divide-border/70">
                        {businesses.map((business) => (
                            <BusinessCard
                                key={business.id}
                                business={business}
                                plans={plans}
                                subscriptionStatuses={subscription_statuses}
                            />
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

function RequestCard({
    item,
    plans,
    subscriptionStatuses,
    endsDefault,
}: {
    item: RequestRow;
    plans: string[];
    subscriptionStatuses: string[];
    endsDefault: string;
}) {
    const approveForm = useForm({
        plan: item.requested_plan,
        subscription_status: 'active',
        subscription_ends_at: endsDefault,
        reviewer_notes: '',
    });
    const rejectForm = useForm({
        reviewer_notes: '',
    });

    return (
        <article className="rounded-2xl border border-border/80 bg-card/80 p-5">
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <ShieldCheck className="size-4 text-primary" />
                <h2 className="font-medium">{item.business.name}</h2>
                <Badge variant="secondary">{item.status}</Badge>
                <Badge>
                    {item.current_plan} → {item.requested_plan}
                </Badge>
            </div>
            <dl className="mb-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt className="text-muted-foreground">Requested by</dt>
                    <dd className="font-medium">
                        {item.requested_by.name} ({item.requested_by.email})
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Transaction code</dt>
                    <dd className="font-medium">{item.transaction_code}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Owner note</dt>
                    <dd className="font-medium">{item.notes || '—'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Current business</dt>
                    <dd className="font-medium">
                        {item.business.plan} /{' '}
                        {item.business.subscription_status}
                    </dd>
                </div>
            </dl>

            {item.status === 'pending' ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    <form
                        className="space-y-3 rounded-xl border border-border/70 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            approveForm.post(
                                `/platform/subscription-requests/${item.id}/approve`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <h3 className="font-medium">Approve</h3>
                        <div className="grid gap-2">
                            <Label>Plan</Label>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={approveForm.data.plan}
                                onChange={(e) =>
                                    approveForm.setData('plan', e.target.value)
                                }
                            >
                                {plans.map((plan) => (
                                    <option key={plan} value={plan}>
                                        {plan}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={approveForm.data.subscription_status}
                                onChange={(e) =>
                                    approveForm.setData(
                                        'subscription_status',
                                        e.target.value,
                                    )
                                }
                            >
                                {subscriptionStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Ends at</Label>
                            <input
                                type="date"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={
                                    approveForm.data.subscription_ends_at ?? ''
                                }
                                onChange={(e) =>
                                    approveForm.setData(
                                        'subscription_ends_at',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Reviewer notes</Label>
                            <textarea
                                className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                value={approveForm.data.reviewer_notes}
                                onChange={(e) =>
                                    approveForm.setData(
                                        'reviewer_notes',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={approveForm.processing}
                            className="w-full"
                        >
                            Approve request
                        </Button>
                    </form>

                    <form
                        className="space-y-3 rounded-xl border border-border/70 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            rejectForm.post(
                                `/platform/subscription-requests/${item.id}/reject`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <h3 className="font-medium">Reject</h3>
                        <div className="grid gap-2">
                            <Label>Reviewer notes</Label>
                            <textarea
                                className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                value={rejectForm.data.reviewer_notes}
                                onChange={(e) =>
                                    rejectForm.setData(
                                        'reviewer_notes',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={rejectForm.errors.reviewer_notes}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={rejectForm.processing}
                            className="w-full"
                        >
                            Reject request
                        </Button>
                    </form>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Reviewed by {item.reviewed_by ?? 'n/a'}
                    {item.reviewer_notes
                        ? ` — ${item.reviewer_notes}`
                        : ''}
                </p>
            )}
        </article>
    );
}

function BusinessCard({
    business,
    plans,
    subscriptionStatuses,
}: {
    business: BusinessRow;
    plans: string[];
    subscriptionStatuses: string[];
}) {
    const form = useForm({
        plan: business.plan,
        subscription_status: business.subscription_status,
        subscription_ends_at: business.subscription_ends_at ?? '',
        reviewer_notes: '',
    });

    return (
        <li className="grid gap-3 p-4 lg:grid-cols-[1fr_auto]">
            <div>
                <p className="font-medium">{business.name}</p>
                <p className="text-sm text-muted-foreground">
                    {business.owner?.name} · {business.owner?.email}
                </p>
            </div>
            <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(
                        `/platform/businesses/${business.id}/subscription`,
                        { preserveScroll: true },
                    );
                }}
            >
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={form.data.plan}
                    onChange={(e) => form.setData('plan', e.target.value)}
                >
                    {plans.map((plan) => (
                        <option key={plan} value={plan}>
                            {plan}
                        </option>
                    ))}
                </select>
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={form.data.subscription_status}
                    onChange={(e) =>
                        form.setData('subscription_status', e.target.value)
                    }
                >
                    {subscriptionStatuses.map((status) => (
                        <option key={status} value={status}>
                            {status}
                        </option>
                    ))}
                </select>
                <input
                    type="date"
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={form.data.subscription_ends_at ?? ''}
                    onChange={(e) =>
                        form.setData('subscription_ends_at', e.target.value)
                    }
                />
                <Button type="submit" size="sm" disabled={form.processing}>
                    Update
                </Button>
            </form>
        </li>
    );
}

PlatformSubscriptionRequestsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Subscription review',
            href: '/platform/subscription-requests',
        },
    ],
};
