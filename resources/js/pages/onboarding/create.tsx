import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/onboarding';

type Props = {
    countries: Record<string, string>;
    currencies: Record<string, string>;
};

type Step = 1 | 2 | 3;

type FormState = {
    name: string;
    country: string;
    currency: string;
    branch_name: string;
    branch_city: string;
    branch_address: string;
    branch_phone: string;
};

export default function OnboardingCreate({ countries, currencies }: Props) {
    const { name } = usePage().props;
    const [step, setStep] = useState<Step>(1);
    const [form, setForm] = useState<FormState>({
        name: '',
        country: 'KE',
        currency: 'KES',
        branch_name: '',
        branch_city: '',
        branch_address: '',
        branch_phone: '',
    });

    const update = (field: keyof FormState, value: string) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    return (
        <>
            <Head title="Business setup wizard" />
            <div className="kospal-shell-bg flex min-h-svh items-center justify-center p-6">
                <div className="w-full max-w-lg space-y-6 rounded-2xl border border-border/80 bg-card/90 p-6 shadow-sm md:p-8">
                    <div className="space-y-2">
                        <p className="font-display text-sm font-semibold tracking-wide text-primary">
                            {name}
                        </p>
                        <h1 className="font-display text-2xl font-semibold">
                            Business setup wizard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Step through business details, your first branch,
                            then confirm before creating your workspace.
                        </p>
                    </div>

                    <ol className="flex flex-wrap gap-2 text-xs">
                        {[
                            { n: 1, label: 'Business' },
                            { n: 2, label: 'Branch' },
                            { n: 3, label: 'Review' },
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
                            <>
                                {step === 1 ? (
                                    <section className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Business name
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                value={form.name}
                                                onChange={(event) =>
                                                    update(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="country">
                                                    Country
                                                </Label>
                                                <select
                                                    id="country"
                                                    name="country"
                                                    required
                                                    value={form.country}
                                                    onChange={(event) =>
                                                        update(
                                                            'country',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                >
                                                    {Object.entries(
                                                        countries,
                                                    ).map(([code, label]) => (
                                                        <option
                                                            key={code}
                                                            value={code}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.country}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="currency">
                                                    Currency
                                                </Label>
                                                <select
                                                    id="currency"
                                                    name="currency"
                                                    required
                                                    value={form.currency}
                                                    onChange={(event) =>
                                                        update(
                                                            'currency',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                >
                                                    {Object.entries(
                                                        currencies,
                                                    ).map(([code, label]) => (
                                                        <option
                                                            key={code}
                                                            value={code}
                                                        >
                                                            {code} — {label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.currency}
                                                />
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
                                            <Label htmlFor="branch_name">
                                                Branch name
                                            </Label>
                                            <Input
                                                id="branch_name"
                                                name="branch_name"
                                                required
                                                placeholder="Main store"
                                                value={form.branch_name}
                                                onChange={(event) =>
                                                    update(
                                                        'branch_name',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.branch_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="branch_city">
                                                City
                                            </Label>
                                            <Input
                                                id="branch_city"
                                                name="branch_city"
                                                value={form.branch_city}
                                                onChange={(event) =>
                                                    update(
                                                        'branch_city',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.branch_city}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="branch_address">
                                                Address
                                            </Label>
                                            <Input
                                                id="branch_address"
                                                name="branch_address"
                                                value={form.branch_address}
                                                onChange={(event) =>
                                                    update(
                                                        'branch_address',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.branch_address}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="branch_phone">
                                                Phone
                                            </Label>
                                            <Input
                                                id="branch_phone"
                                                name="branch_phone"
                                                value={form.branch_phone}
                                                onChange={(event) =>
                                                    update(
                                                        'branch_phone',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.branch_phone}
                                            />
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
                                                disabled={
                                                    form.branch_name.trim() ===
                                                    ''
                                                }
                                                onClick={() => setStep(3)}
                                            >
                                                Review
                                            </Button>
                                        </div>
                                    </section>
                                ) : null}

                                {step === 3 ? (
                                    <section className="space-y-4">
                                        <dl className="grid gap-3 rounded-md border p-4 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Business
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.name}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Country / currency
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.country} ·{' '}
                                                    {form.currency}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Branch
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.branch_name}
                                                    {form.branch_city
                                                        ? ` — ${form.branch_city}`
                                                        : ''}
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setStep(2)}
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
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
