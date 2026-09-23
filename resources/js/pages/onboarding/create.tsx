import { Form, Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { EditionCards } from '@/components/edition-cards';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EditionCard } from '@/lib/editions';
import { cn } from '@/lib/utils';
import { store } from '@/routes/onboarding';

type ReferralPreview = {
    code: string;
    referrer_name: string;
    expires_at: string;
    usable: boolean;
};

type Props = {
    countries: Record<string, string>;
    currencies: Record<string, string>;
    plans: EditionCard[];
    default_trial_edition: string;
    trial_days: number;
    referral?: ReferralPreview | null;
};

type Step = 1 | 2 | 3 | 4;

type FormState = {
    name: string;
    country: string;
    currency: string;
    branch_name: string;
    branch_city: string;
    branch_address: string;
    branch_phone: string;
    trial_edition: string;
    referral_code: string;
};

const businessFields = ['name', 'country', 'currency'] as const;
const branchFields = [
    'branch_name',
    'branch_city',
    'branch_address',
    'branch_phone',
] as const;

function stepForErrors(
    errors: Record<string, string>,
    isDesktop: boolean,
): Step | null {
    if (businessFields.some((field) => errors[field]) || errors.referral_code) {
        return 1;
    }

    if (branchFields.some((field) => errors[field])) {
        return 2;
    }

    if (isDesktop && errors.trial_edition) {
        return 3;
    }

    return null;
}

export default function OnboardingCreate({
    countries,
    currencies,
    plans,
    default_trial_edition,
    trial_days,
    referral,
}: Props) {
    const { name, deployment } = usePage().props;
    const isDesktop = deployment.is_desktop;
    const [step, setStep] = useState<Step>(1);
    const [form, setForm] = useState<FormState>({
        name: '',
        country: 'KE',
        currency: 'KES',
        branch_name: '',
        branch_city: '',
        branch_address: '',
        branch_phone: '',
        trial_edition: default_trial_edition || 'pro',
        referral_code: referral?.code ?? '',
    });

    const update = (field: keyof FormState, value: string) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const reviewStep: Step = isDesktop ? 4 : 3;
    const selectedPlan = plans.find((plan) => plan.key === form.trial_edition);

    return (
        <>
            <Head title="Business setup wizard" />
            <div className="kospal-shell-bg flex min-h-svh items-center justify-center p-6 pt-[max(1.5rem,env(safe-area-inset-top))]">
                <div
                    className={cn(
                        'w-full space-y-6 rounded-2xl border border-border/80 bg-card/90 p-6 shadow-sm md:p-8',
                        isDesktop ? 'max-w-4xl' : 'max-w-lg',
                    )}
                >
                    <div className="space-y-2">
                        <p className="font-display text-sm font-semibold tracking-wide text-primary">
                            {name}
                        </p>
                        <h1 className="font-display text-2xl font-semibold">
                            Business setup wizard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {isDesktop
                                ? 'Set up the business, first branch, and the edition you want to trial.'
                                : 'Step through business details, your first branch, then confirm before creating your workspace.'}
                        </p>
                    </div>

                    <ol className="flex flex-wrap gap-2 text-xs">
                        {[
                            { n: 1, label: 'Business' },
                            { n: 2, label: 'Branch' },
                            ...(isDesktop
                                ? [{ n: 3, label: 'Edition' }]
                                : []),
                            { n: reviewStep, label: 'Review' },
                        ].map((item) => (
                            <li
                                key={item.n}
                                className={
                                    step === item.n
                                        ? 'rounded-md bg-primary px-2 py-1 text-primary-foreground'
                                        : 'rounded-md bg-muted px-2 py-1 text-muted-foreground'
                                }
                            >
                                {item.n}. {item.label}
                            </li>
                        ))}
                    </ol>

                    <Form
                        {...store.form()}
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <OnboardingSteps
                                step={step}
                                setStep={setStep}
                                form={form}
                                update={update}
                                countries={countries}
                                currencies={currencies}
                                plans={plans}
                                trialDays={trial_days}
                                selectedPlan={selectedPlan}
                                isDesktop={isDesktop}
                                reviewStep={reviewStep}
                                processing={processing}
                                errors={errors}
                                referral={referral}
                            />
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

function OnboardingSteps({
    step,
    setStep,
    form,
    update,
    countries,
    currencies,
    plans,
    trialDays,
    selectedPlan,
    isDesktop,
    reviewStep,
    processing,
    errors,
    referral,
}: {
    step: Step;
    setStep: (step: Step) => void;
    form: FormState;
    update: (field: keyof FormState, value: string) => void;
    countries: Record<string, string>;
    currencies: Record<string, string>;
    plans: EditionCard[];
    trialDays: number;
    selectedPlan: EditionCard | undefined;
    isDesktop: boolean;
    reviewStep: Step;
    processing: boolean;
    errors: Record<string, string>;
    referral?: ReferralPreview | null;
}) {
    useEffect(() => {
        const next = stepForErrors(errors, isDesktop);

        if (next !== null) {
            setStep(next);
        }
    }, [errors, isDesktop, setStep]);

    return (
        <>
            {step !== 1 ? (
                <>
                    <input type="hidden" name="name" value={form.name} />
                    <input type="hidden" name="country" value={form.country} />
                    <input
                        type="hidden"
                        name="currency"
                        value={form.currency}
                    />
                </>
            ) : null}
            {step !== 2 ? (
                <>
                    <input
                        type="hidden"
                        name="branch_name"
                        value={form.branch_name}
                    />
                    <input
                        type="hidden"
                        name="branch_city"
                        value={form.branch_city}
                    />
                    <input
                        type="hidden"
                        name="branch_address"
                        value={form.branch_address}
                    />
                    <input
                        type="hidden"
                        name="branch_phone"
                        value={form.branch_phone}
                    />
                </>
            ) : null}
            {isDesktop && step !== 3 ? (
                <input
                    type="hidden"
                    name="trial_edition"
                    value={form.trial_edition}
                />
            ) : null}
            {step !== 1 ? (
                <input
                    type="hidden"
                    name="referral_code"
                    value={form.referral_code}
                />
            ) : null}

            {step === 1 ? (
                <section className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Business name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            value={form.name}
                            onChange={(event) =>
                                update('name', event.target.value)
                            }
                        />
                        <InputError message={errors.name} />
                    </div>

                    {referral?.usable ? (
                        <p className="rounded-md border border-border/80 bg-muted/40 px-3 py-2 text-sm">
                            Invite from {referral.referrer_name}. Stay active
                            for 1 week after setup and you both get 10% off the
                            first payment.
                        </p>
                    ) : null}

                    <div className="grid gap-2">
                        <Label htmlFor="referral_code">
                            Invite code (optional)
                        </Label>
                        <Input
                            id="referral_code"
                            name="referral_code"
                            value={form.referral_code}
                            onChange={(event) =>
                                update('referral_code', event.target.value)
                            }
                            placeholder="KSP-XXXXXX"
                        />
                        <InputError message={errors.referral_code} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="country">Country</Label>
                            <select
                                id="country"
                                name="country"
                                required
                                value={form.country}
                                onChange={(event) =>
                                    update('country', event.target.value)
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                {Object.entries(countries).map(
                                    ([code, label]) => (
                                        <option key={code} value={code}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.country} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="currency">Currency</Label>
                            <select
                                id="currency"
                                name="currency"
                                required
                                value={form.currency}
                                onChange={(event) =>
                                    update('currency', event.target.value)
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                {Object.entries(currencies).map(
                                    ([code, label]) => (
                                        <option key={code} value={code}>
                                            {code} — {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.currency} />
                        </div>
                    </div>

                    <Button
                        type="button"
                        className="w-full"
                        disabled={form.name.trim() === ''}
                        onClick={() => setStep(2)}
                    >
                        Continue
                    </Button>
                </section>
            ) : null}

            {step === 2 ? (
                <section className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="branch_name">Branch name</Label>
                        <Input
                            id="branch_name"
                            name="branch_name"
                            required
                            placeholder="Main store"
                            value={form.branch_name}
                            onChange={(event) =>
                                update('branch_name', event.target.value)
                            }
                        />
                        <InputError message={errors.branch_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="branch_city">City</Label>
                        <Input
                            id="branch_city"
                            name="branch_city"
                            value={form.branch_city}
                            onChange={(event) =>
                                update('branch_city', event.target.value)
                            }
                        />
                        <InputError message={errors.branch_city} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="branch_address">Address</Label>
                        <Input
                            id="branch_address"
                            name="branch_address"
                            value={form.branch_address}
                            onChange={(event) =>
                                update('branch_address', event.target.value)
                            }
                        />
                        <InputError message={errors.branch_address} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="branch_phone">Phone</Label>
                        <Input
                            id="branch_phone"
                            name="branch_phone"
                            value={form.branch_phone}
                            onChange={(event) =>
                                update('branch_phone', event.target.value)
                            }
                        />
                        <InputError message={errors.branch_phone} />
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(1)}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            disabled={form.branch_name.trim() === ''}
                            onClick={() => setStep(3)}
                        >
                            {isDesktop ? 'Choose edition' : 'Review'}
                        </Button>
                    </div>
                </section>
            ) : null}

            {isDesktop && step === 3 ? (
                <section className="space-y-4">
                    <input
                        type="hidden"
                        name="trial_edition"
                        value={form.trial_edition}
                    />
                    <p className="text-sm text-muted-foreground">
                        Trial {trialDays} days on the edition you expect to buy.
                        You can upgrade, renew, or downgrade later by paying
                        outside the app and activating a matching key.
                    </p>
                    <EditionCards
                        plans={plans}
                        selected={form.trial_edition}
                        onSelect={(key) => update('trial_edition', key)}
                    />
                    <InputError message={errors.trial_edition} />
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(2)}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            disabled={form.trial_edition === ''}
                            onClick={() => setStep(4)}
                        >
                            Review
                        </Button>
                    </div>
                </section>
            ) : null}

            {step === reviewStep ? (
                <section className="space-y-4">
                    <dl className="grid gap-3 rounded-md border p-4 text-sm">
                        <div>
                            <dt className="text-muted-foreground">Business</dt>
                            <dd className="font-medium">{form.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Country / currency
                            </dt>
                            <dd className="font-medium">
                                {form.country} · {form.currency}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Branch</dt>
                            <dd className="font-medium">
                                {form.branch_name}
                                {form.branch_city
                                    ? ` — ${form.branch_city}`
                                    : ''}
                            </dd>
                        </div>
                        {isDesktop ? (
                            <div>
                                <dt className="text-muted-foreground">
                                    Trial edition
                                </dt>
                                <dd className="font-medium">
                                    {selectedPlan?.name ?? form.trial_edition} ·{' '}
                                    {trialDays} days
                                </dd>
                            </div>
                        ) : null}
                        {form.referral_code ? (
                            <div>
                                <dt className="text-muted-foreground">
                                    Invite code
                                </dt>
                                <dd className="font-medium">
                                    {form.referral_code}
                                </dd>
                            </div>
                        ) : null}
                    </dl>

                    <InputError
                        message={
                            errors.name ||
                            errors.country ||
                            errors.currency ||
                            errors.branch_name ||
                            errors.branch_city ||
                            errors.branch_address ||
                            errors.branch_phone ||
                            errors.trial_edition ||
                            errors.referral_code
                        }
                    />

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setStep(isDesktop ? 3 : 2)
                            }
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            className="flex-1"
                            disabled={processing}
                        >
                            Create business
                        </Button>
                    </div>
                </section>
            ) : null}
        </>
    );
}
