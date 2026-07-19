import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/onboarding';

type Props = {
    countries: Record<string, string>;
    currencies: Record<string, string>;
};

export default function OnboardingCreate({ countries, currencies }: Props) {
    const { name } = usePage().props;

    return (
        <>
            <Head title="Create your business" />
            <div className="kospal-shell-bg flex min-h-svh items-center justify-center p-6">
                <div className="w-full max-w-lg space-y-6 rounded-2xl border border-border/80 bg-card/90 p-6 shadow-sm md:p-8">
                    <div className="space-y-2">
                        <p className="font-display text-sm font-semibold tracking-wide text-primary">
                            {name}
                        </p>
                        <h1 className="font-display text-2xl font-semibold">
                            Set up your business
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create your retail business and first branch. Plan
                            limits are enforced on the server after setup.
                        </p>
                    </div>

                    <Form
                        {...store.form()}
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Business name</Label>
                                    <Input id="name" name="name" required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="country">Country</Label>
                                        <select
                                            id="country"
                                            name="country"
                                            defaultValue="KE"
                                            required
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        >
                                            {Object.entries(countries).map(
                                                ([code, label]) => (
                                                    <option
                                                        key={code}
                                                        value={code}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError message={errors.country} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">
                                            Currency
                                        </Label>
                                        <select
                                            id="currency"
                                            name="currency"
                                            defaultValue="KES"
                                            required
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        >
                                            {Object.entries(currencies).map(
                                                ([code, label]) => (
                                                    <option
                                                        key={code}
                                                        value={code}
                                                    >
                                                        {code} — {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError message={errors.currency} />
                                    </div>
                                </div>

                                <div className="border-t border-border/70 pt-4">
                                    <h2 className="mb-3 text-sm font-medium">
                                        First branch
                                    </h2>
                                    <div className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="branch_name">
                                                Branch name
                                            </Label>
                                            <Input
                                                id="branch_name"
                                                name="branch_name"
                                                required
                                                placeholder="Main store"
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
                                            />
                                            <InputError
                                                message={errors.branch_phone}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    Create business
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
