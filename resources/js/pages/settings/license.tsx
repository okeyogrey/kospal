import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Lock } from 'lucide-react';
import LicenseController from '@/actions/App/Http/Controllers/Settings/LicenseController';
import { EditionCards } from '@/components/edition-cards';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaymentInstructionsCard } from '@/components/payment-instructions-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    type EditionCard,
    type EditionChangeType,
    type EditionRequestRecord,
    type PaymentInstructions,
    changeTypeLabel,
    editionRequestCopy,
    featureLabel,
    selectEditionLabel,
    suggestedEdition,
} from '@/lib/editions';
import { edit } from '@/routes/license';

type LicenseStatus = {
    machine_id: string;
    status: string;
    activation_mode: string | null;
    plan: string;
    expires_at: string | null;
    days_remaining: number | null;
    licensed_at: string | null;
    licensed_machine_id: string | null;
    license_id: string | null;
    allows_write_access: boolean;
    is_trial: boolean;
    is_expired: boolean;
    features: string[];
};

type LicenseBranch = {
    id: number;
    name: string;
    is_active: boolean;
    plan_paused: boolean;
};

function KeepBranchPicker({ branches }: { branches: LicenseBranch[] }) {
    const active = branches.filter((branch) => branch.is_active);

    if (active.length <= 1) {
        return null;
    }

    return (
        <fieldset className="space-y-2 rounded-md border border-border/80 p-3">
            <legend className="text-sm font-medium">
                Locations that stay open
            </legend>
            <p className="text-sm text-muted-foreground">
                If this license allows fewer shops than you have open, tick the
                ones to keep. Extra shops are paused — not deleted — and cannot
                be swapped back later unless you upgrade. Starter keeps 1, Pro
                keeps up to 3, Enterprise keeps up to 10.
            </p>
            {active.map((branch) => (
                <label
                    key={branch.id}
                    className="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="keep_branch_ids[]"
                        value={branch.id}
                        className="size-4"
                    />
                    {branch.name}
                </label>
            ))}
        </fieldset>
    );
}

function statusLabel(status: string): string {
    switch (status) {
        case 'trial':
            return 'Trial';
        case 'active':
            return 'Active';
        case 'expired':
            return 'Expired';
        case 'suspended':
            return 'Suspended';
        default:
            return status;
    }
}

export default function LicenseSettings({
    license,
    limits,
    plans,
    branches,
    payment_instructions,
    requests,
    has_pending_request,
    business,
    pricing,
}: {
    license: LicenseStatus;
    limits: {
        max_branches: number;
        active_branches: number;
        max_staff: number | null;
        staff_seats: number;
        features: string[];
    };
    plans: EditionCard[];
    branches: LicenseBranch[];
    payment_instructions: PaymentInstructions;
    requests: EditionRequestRecord[];
    has_pending_request: boolean;
    business: { id: number; name: string };
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
    const currentPlan = plans.find((plan) => plan.key === license.plan);
    const requestForm = useForm({
        requested_plan: suggestedEdition(license.plan),
        transaction_code: '',
        notes: '',
    });
    const selectedPlan =
        plans.find((plan) => plan.key === requestForm.data.requested_plan) ??
        currentPlan;
    const changeType: EditionChangeType =
        selectedPlan?.change_type ?? 'renew';
    const [, copy] = useClipboard();
    const copiedText = editionRequestCopy({
        businessName: business.name,
        machineId: license.machine_id,
        currentPlan: currentPlan?.name ?? license.plan,
        currentStatus: statusLabel(license.status),
        requestedPlan: selectedPlan?.name ?? requestForm.data.requested_plan,
        changeType,
    });

    return (
        <>
            <Head title="License settings" />

            <h1 className="sr-only">License settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="License"
                    description={`Choose an edition for ${business.name}, pay outside the app, then activate the matching key. Choosing an edition does not unlock it.`}
                />

                {flash?.error ? (
                    <Alert variant="destructive">
                        <Lock className="size-4" />
                        <AlertTitle>Action blocked</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>License updated</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {license.is_expired ? (
                    <Alert variant="destructive">
                        <Lock className="size-4" />
                        <AlertTitle>License expired</AlertTitle>
                        <AlertDescription>
                            The app is read-only. Renew or upgrade below, then
                            activate the key you receive.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {license.is_trial && !license.is_expired ? (
                    <Alert>
                        <KeyRound className="size-4" />
                        <AlertTitle>
                            {license.days_remaining === null
                                ? 'Trial is active'
                                : `${license.days_remaining} day${license.days_remaining === 1 ? '' : 's'} left on this ${currentPlan?.name ?? license.plan} trial`}
                        </AlertTitle>
                        <AlertDescription>
                            Buy the edition you want to keep. Features stay on
                            this trial until it ends or you activate a key.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {pricing && pricing.available_percent > 0 ? (
                    <Alert>
                        <AlertTitle>
                            Referral credit: {pricing.discount_percent}% off
                        </AlertTitle>
                        <AlertDescription>
                            Pay {pricing.due_formatted} instead of{' '}
                            {pricing.list_formatted} when you activate.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <section className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge>{statusLabel(license.status)}</Badge>
                        {license.activation_mode ? (
                            <Badge variant="secondary">
                                {license.activation_mode}
                            </Badge>
                        ) : null}
                        <Badge variant="outline">
                            {currentPlan?.name ?? license.plan}
                        </Badge>
                    </div>

                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Machine ID</dt>
                            <dd className="font-mono text-xs break-all">
                                {license.machine_id}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Expires</dt>
                            <dd className="font-medium">
                                {license.expires_at ?? 'No expiry'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Active branches
                            </dt>
                            <dd className="font-medium">
                                {limits.active_branches}/{limits.max_branches}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Staff seats</dt>
                            <dd className="font-medium">
                                {limits.staff_seats}
                                {limits.max_staff === null
                                    ? ' (unlimited)'
                                    : `/${limits.max_staff}`}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">Features</dt>
                            <dd className="font-medium">
                                {license.features
                                    .map((feature) => featureLabel(feature))
                                    .join(', ')}
                            </dd>
                        </div>
                    </dl>
                </section>

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Choose an edition"
                        description="Upgrade unlocks more shops and features. Renew keeps this edition with a new expiry. Downgrade pauses extra shops."
                    />
                    <EditionCards
                        plans={plans}
                        selected={requestForm.data.requested_plan}
                        onSelect={(key) =>
                            requestForm.setData('requested_plan', key)
                        }
                        disabled={has_pending_request}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
                    <PaymentInstructionsCard
                        instructions={payment_instructions}
                    />

                    <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                        <h2 className="mb-2 font-medium">
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
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Pay using the instructions, copy this request, and
                            send it with your proof. Then paste the{' '}
                            {selectedPlan?.name ?? 'matching'} key below. This
                            save does not change your edition.
                        </p>
                        {has_pending_request ? (
                            <p className="text-sm text-muted-foreground">
                                You already have a pending edition request. Send
                                those details with payment, then activate the
                                matching key.
                            </p>
                        ) : (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    requestForm.post(
                                        LicenseController.storeEditionRequest.url(),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                requestForm.reset(
                                                    'transaction_code',
                                                    'notes',
                                                ),
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="transaction_code">
                                        Transaction code (optional)
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
                                        placeholder="M-Pesa receipt or bank ref"
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
                                        className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
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
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => copy(copiedText)}
                                    >
                                        Copy request details
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={requestForm.processing}
                                        className="flex-1"
                                    >
                                        Save {changeTypeLabel(changeType).toLowerCase()} request
                                    </Button>
                                </div>
                            </form>
                        )}
                        {has_pending_request ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-3 w-full"
                                onClick={() => copy(copiedText)}
                            >
                                Copy request details
                            </Button>
                        ) : null}
                    </section>
                </div>

                {requests.length > 0 ? (
                    <section className="rounded-2xl border border-border/80 bg-card/80">
                        <div className="border-b border-border/70 px-4 py-3 font-medium">
                            Edition requests
                        </div>
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
                                        {item.transaction_code ? (
                                            <p className="text-muted-foreground">
                                                Code: {item.transaction_code}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Badge variant="secondary">
                                        {item.status}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Activate the matching key"
                        description="Fulfillment happens here. The key’s edition becomes your plan — not the edition you selected above."
                    />
                    <Form
                        {...LicenseController.activateOnline.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="license_key">
                                        Online license key
                                    </Label>
                                    <Input
                                        id="license_key"
                                        name="license_key"
                                        className="font-mono text-xs"
                                        placeholder="KOS1...."
                                        required
                                    />
                                    <InputError message={errors.license_key} />
                                </div>
                                <KeepBranchPicker branches={branches} />
                                <InputError message={errors.keep_branch_ids} />
                                <Button type="submit" disabled={processing}>
                                    Activate online
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Offline activation"
                        description="Send your Machine ID with the request. Paste the machine-bound code you receive."
                    />
                    <Form
                        {...LicenseController.activateOffline.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="activation_code">
                                        Activation code
                                    </Label>
                                    <textarea
                                        id="activation_code"
                                        name="activation_code"
                                        className="border-input bg-background min-h-24 w-full rounded-md border px-3 py-2 font-mono text-xs"
                                        placeholder="KOS1...."
                                        required
                                    />
                                    <InputError
                                        message={errors.activation_code}
                                    />
                                </div>
                                <KeepBranchPicker branches={branches} />
                                <InputError message={errors.keep_branch_ids} />
                                <Button type="submit" disabled={processing}>
                                    Activate offline
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

LicenseSettings.layout = {
    breadcrumbs: [{ title: 'License settings', href: edit() }],
};
