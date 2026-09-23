import { Head, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Lock } from 'lucide-react';
import { EditionCards } from '@/components/edition-cards';
import InputError from '@/components/input-error';
import { PaymentInstructionsCard } from '@/components/payment-instructions-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    type EditionCard,
    type EditionChangeType,
    type EditionRequestRecord,
    type PaymentInstructions,
    changeTypeLabel,
    featureLabel,
    selectEditionLabel,
    suggestedEdition,
} from '@/lib/editions';
import { index as subscriptionIndex } from '@/routes/subscription';
import { store } from '@/routes/subscription/requests';

function statusMessage(status: string): { title: string; body: string } {
    switch (status) {
        case 'pending':
            return {
                title: 'Subscription pending approval',
                body: 'Your account is read-only until a platform admin verifies payment. Choose an edition, pay, then submit your transaction code.',
            };
        case 'expired':
            return {
                title: 'Subscription expired',
                body: 'Existing data stays visible. Renew or change edition by paying and submitting a new transaction code.',
            };
        case 'suspended':
            return {
                title: 'Subscription suspended',
                body: 'Operational actions are blocked. Submit a new payment request for review.',
            };
        default:
            return {
                title: 'Subscription active',
                body: 'Choose upgrade, renew, or downgrade, pay outside the app, then submit proof. Features change only after approval.',
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
    pricing,
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
    plans: EditionCard[];
    payment_instructions: PaymentInstructions | null;
    requests: EditionRequestRecord[];
    has_pending_request: boolean;
    pricing?: {
        discount_percent: number;
        list_formatted: string;
        due_formatted: string;
        available_percent: number;
    };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;
    const requestForm = useForm({
        requested_plan: suggestedEdition(business.plan),
        transaction_code: '',
        notes: '',
    });
    const notice = statusMessage(business.subscription_status);
    const currentPlan = plans.find((plan) => plan.key === business.plan);
    const selectedPlan =
        plans.find((plan) => plan.key === requestForm.data.requested_plan) ??
        currentPlan;
    const changeType: EditionChangeType =
        selectedPlan?.change_type ?? 'renew';

    return (
        <>
            <Head title="Subscription" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Subscription
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose an edition for {business.name}, pay offline, then
                        submit proof. Approval switches the plan — selecting one
                        here does not.
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
                        <Badge>{currentPlan?.name ?? business.plan}</Badge>
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
                                    .map((feature) => featureLabel(feature))
                                    .join(', ')}
                            </dd>
                        </div>
                    </dl>
                </section>

                {pricing && pricing.available_percent > 0 ? (
                    <Alert>
                        <AlertTitle>
                            Referral credit: {pricing.discount_percent}% off
                        </AlertTitle>
                        <AlertDescription>
                            Pay {pricing.due_formatted} instead of{' '}
                            {pricing.list_formatted} for this first paid period.
                            {pricing.available_percent > 100
                                ? ` Extra ${pricing.available_percent - 100}% stays for later payments.`
                                : ''}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <EditionCards
                    plans={plans}
                    selected={requestForm.data.requested_plan}
                    onSelect={(key) =>
                        requestForm.setData('requested_plan', key)
                    }
                    disabled={has_pending_request}
                />

                <div className="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
                    <PaymentInstructionsCard
                        instructions={payment_instructions}
                    />

                    <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                        <h2 className="mb-3 font-medium">
                            Submit {changeTypeLabel(changeType).toLowerCase()} proof
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
                                    requestForm.post(store.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            requestForm.reset(
                                                'transaction_code',
                                                'notes',
                                            ),
                                    });
                                }}
                            >
                                <p className="text-sm text-muted-foreground">
                                    {selectEditionLabel(
                                        selectedPlan ?? {
                                            key: requestForm.data.requested_plan,
                                            name: requestForm.data.requested_plan,
                                            description: '',
                                            max_branches: 0,
                                            max_staff: null,
                                            features: [],
                                            is_current: false,
                                            change_type: changeType,
                                        },
                                    )}
                                    . Features change only after a platform
                                    admin approves this payment.
                                </p>
                                <div className="grid gap-2">
                                    <Label htmlFor="transaction_code">
                                        Transaction code
                                    </Label>
                                    <input
                                        id="transaction_code"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        value={requestForm.data.transaction_code}
                                        onChange={(event) =>
                                            requestForm.setData(
                                                'transaction_code',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="e.g. MPesa receipt or bank ref"
                                        required
                                    />
                                    <InputError
                                        message={
                                            requestForm.errors.transaction_code
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">
                                        Note (optional)
                                    </Label>
                                    <textarea
                                        id="notes"
                                        className="border-input bg-background min-h-24 w-full rounded-md border px-3 py-2 text-sm"
                                        value={requestForm.data.notes}
                                        onChange={(event) =>
                                            requestForm.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={requestForm.errors.notes}
                                    />
                                </div>
                                <InputError
                                    message={requestForm.errors.requested_plan}
                                />
                                <Button
                                    type="submit"
                                    disabled={requestForm.processing}
                                    className="w-full"
                                >
                                    Submit {changeTypeLabel(changeType).toLowerCase()} for approval
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
                            No edition requests yet.
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
                                            {changeTypeLabel(item.change_type)}{' '}
                                            · {item.current_plan} →{' '}
                                            {item.requested_plan}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Code: {item.transaction_code}
                                        </p>
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
