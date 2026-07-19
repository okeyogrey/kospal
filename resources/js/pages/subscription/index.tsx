import { Head, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index as subscriptionIndex } from '@/routes/subscription';
import { store } from '@/routes/subscription/requests';

type PlanCard = {
    key: string;
    name: string;
    description: string;
    max_branches: number;
    max_staff: number | null;
    features: string[];
};

type PaymentInstructions = {
    title: string;
    body: string;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
    mobile_money: string | null;
    support_note: string | null;
};

const FEATURE_LABELS: Record<string, string> = {
    core: 'Core POS, catalog, and inventory',
    advanced_reports: 'Advanced reports',
    csv_export: 'CSV export',
    stock_transfers: 'Stock transfers',
    audit_logs: 'Enterprise audit logs',
    consolidated_reports: 'Consolidated multi-branch reports',
};

function statusMessage(status: string): { title: string; body: string } {
    switch (status) {
        case 'pending':
            return {
                title: 'Subscription pending approval',
                body: 'Your account is read-only until a platform admin verifies payment. Follow the instructions below, then submit your transaction code.',
            };
        case 'expired':
            return {
                title: 'Subscription expired',
                body: 'Existing data stays visible. Renew by paying with the instructions below and submitting a new transaction code.',
            };
        case 'suspended':
            return {
                title: 'Subscription suspended',
                body: 'Operational actions are blocked. Contact support or submit a new payment request for review.',
            };
        default:
            return {
                title: 'Subscription active',
                body: 'You can request a plan change after completing an external payment.',
            };
    }
}

export default function SubscriptionIndex({
    business,
    limits,
    plans,
    payment_instructions,
    requests,
    has_pending_request,
}: {
    business: {
        id: number;
        name: string;
        plan: string;
        subscription_status: string;
        subscription_ends_at: string | null;
        allows_write_access: boolean;
    };
    limits: {
        max_branches: number;
        active_branches: number;
        max_staff: number | null;
        staff_seats: number;
        features: string[];
    };
    plans: PlanCard[];
    payment_instructions: PaymentInstructions;
    requests: Array<{
        id: number;
        requested_plan: string;
        current_plan: string;
        status: string;
        notes: string | null;
        transaction_code: string;
        reviewer_notes: string | null;
        reviewed_by: string | null;
        created_at: string | null;
        reviewed_at: string | null;
    }>;
    has_pending_request: boolean;
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;
    const form = useForm({
        requested_plan: business.plan === 'starter' ? 'pro' : business.plan,
        transaction_code: '',
        notes: '',
    });
    const notice = statusMessage(business.subscription_status);

    return (
        <>
            <Head title="Subscription" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Subscription
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Compare plans, pay offline, and submit your transaction
                        code for {business.name}.
                    </p>
                </div>

                {flash?.error ? (
                    <Alert variant="destructive">
                        <Lock className="size-4" />
                        <AlertTitle>Action blocked</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {!business.allows_write_access ? (
                    <Alert>
                        <Lock className="size-4" />
                        <AlertTitle>{notice.title}</AlertTitle>
                        <AlertDescription>{notice.body}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Request received</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <CreditCard className="size-4 text-primary" />
                        <h2 className="font-medium">Current status</h2>
                        <Badge>{business.plan}</Badge>
                        <Badge variant="secondary">
                            {business.subscription_status}
                        </Badge>
                        {business.subscription_ends_at ? (
                            <span className="text-sm text-muted-foreground">
                                Ends {business.subscription_ends_at}
                            </span>
                        ) : null}
                    </div>
                    <dl className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="text-muted-foreground">
                                Active branches
                            </dt>
                            <dd className="font-medium">
                                {limits.active_branches}/{limits.max_branches}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Staff seats
                            </dt>
                            <dd className="font-medium">
                                {limits.staff_seats}
                                {limits.max_staff === null
                                    ? ' (unlimited)'
                                    : `/${limits.max_staff}`}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Features</dt>
                            <dd className="font-medium">
                                {limits.features
                                    .map(
                                        (feature) =>
                                            FEATURE_LABELS[feature] ?? feature,
                                    )
                                    .join(', ')}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const isCurrent = plan.key === business.plan;

                        return (
                            <div
                                key={plan.key}
                                className={`rounded-2xl border p-5 ${
                                    isCurrent
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border/80 bg-card/80'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2">
                                    <h2 className="font-display text-xl font-semibold">
                                        {plan.name}
                                    </h2>
                                    {isCurrent ? (
                                        <Badge>Current</Badge>
                                    ) : null}
                                </div>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {plan.description}
                                </p>
                                <p className="mb-3 text-sm font-medium">
                                    {plan.max_branches} branches ·{' '}
                                    {plan.max_staff ?? 'Unlimited'} staff
                                </p>
                                <ul className="space-y-1.5 text-sm">
                                    {plan.features.map((feature) => (
                                        <li key={feature}>
                                            {FEATURE_LABELS[feature] ??
                                                feature}
                                        </li>
                                    ))}
                                </ul>
                                {!isCurrent && !has_pending_request ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-4 w-full"
                                        onClick={() =>
                                            form.setData(
                                                'requested_plan',
                                                plan.key,
                                            )
                                        }
                                    >
                                        Select {plan.name}
                                    </Button>
                                ) : null}
                            </div>
                        );
                    })}
                </section>

                <div className="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
                    <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                        <h2 className="mb-2 font-medium">
                            {payment_instructions.title}
                        </h2>
                        <p className="mb-4 whitespace-pre-wrap text-sm text-muted-foreground">
                            {payment_instructions.body}
                        </p>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            {payment_instructions.bank_name ? (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Bank
                                    </dt>
                                    <dd className="font-medium">
                                        {payment_instructions.bank_name}
                                    </dd>
                                </div>
                            ) : null}
                            {payment_instructions.account_name ? (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Account name
                                    </dt>
                                    <dd className="font-medium">
                                        {payment_instructions.account_name}
                                    </dd>
                                </div>
                            ) : null}
                            {payment_instructions.account_number ? (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Account number
                                    </dt>
                                    <dd className="font-medium">
                                        {payment_instructions.account_number}
                                    </dd>
                                </div>
                            ) : null}
                            {payment_instructions.mobile_money ? (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Mobile money
                                    </dt>
                                    <dd className="font-medium">
                                        {payment_instructions.mobile_money}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                        {payment_instructions.support_note ? (
                            <p className="mt-4 text-sm text-muted-foreground">
                                {payment_instructions.support_note}
                            </p>
                        ) : null}
                    </section>

                    <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                        <h2 className="mb-3 font-medium">
                            Submit payment proof
                        </h2>
                        {has_pending_request ? (
                            <p className="text-sm text-muted-foreground">
                                You already have a pending request. Wait for
                                platform review before submitting another.
                            </p>
                        ) : (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(store.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            form.reset(
                                                'transaction_code',
                                                'notes',
                                            ),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_plan">
                                        Plan
                                    </Label>
                                    <select
                                        id="requested_plan"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        value={form.data.requested_plan}
                                        onChange={(e) =>
                                            form.setData(
                                                'requested_plan',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {plans.map((plan) => (
                                            <option
                                                key={plan.key}
                                                value={plan.key}
                                            >
                                                {plan.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.requested_plan}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="transaction_code">
                                        Transaction code
                                    </Label>
                                    <input
                                        id="transaction_code"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        value={form.data.transaction_code}
                                        onChange={(e) =>
                                            form.setData(
                                                'transaction_code',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. MPesa receipt or bank ref"
                                        required
                                    />
                                    <InputError
                                        message={form.errors.transaction_code}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">
                                        Note (optional)
                                    </Label>
                                    <textarea
                                        id="notes"
                                        className="border-input bg-background min-h-24 w-full rounded-md border px-3 py-2 text-sm"
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.notes} />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    Submit for approval
                                </Button>
                            </form>
                        )}
                    </section>
                </div>

                <section className="rounded-2xl border border-border/80 bg-card/80">
                    <div className="border-b border-border/70 px-4 py-3 font-medium">
                        Request history
                    </div>
                    {requests.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No subscription requests yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border/70">
                            {requests.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-col gap-2 p-4 text-sm sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.current_plan} →{' '}
                                            {item.requested_plan}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Code: {item.transaction_code}
                                        </p>
                                        {item.notes ? (
                                            <p className="text-muted-foreground">
                                                Note: {item.notes}
                                            </p>
                                        ) : null}
                                        {item.reviewer_notes ? (
                                            <p className="text-muted-foreground">
                                                Reviewer:{' '}
                                                {item.reviewer_notes}
                                                {item.reviewed_by
                                                    ? ` (${item.reviewed_by})`
                                                    : ''}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Badge variant="secondary">
                                        {item.status}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

SubscriptionIndex.layout = {
    breadcrumbs: [{ title: 'Subscription', href: subscriptionIndex() }],
};
