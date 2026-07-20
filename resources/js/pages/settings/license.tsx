import { Form, Head, usePage } from '@inertiajs/react';
import { KeyRound, Lock } from 'lucide-react';
import LicenseController from '@/actions/App/Http/Controllers/Settings/LicenseController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/license';

const FEATURE_LABELS: Record<string, string> = {
    core: 'Core POS, catalog, and inventory',
    advanced_reports: 'Advanced reports',
    csv_export: 'CSV export',
    stock_transfers: 'Stock transfers',
    audit_logs: 'Enterprise audit logs',
    consolidated_reports: 'Consolidated multi-branch reports',
};

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

type PlanCard = {
    key: string;
    name: string;
    description: string;
    max_branches: number;
    max_staff: number | null;
    features: string[];
};

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
    business,
}: {
    license: LicenseStatus;
    limits: {
        max_branches: number;
        active_branches: number;
        max_staff: number | null;
        staff_seats: number;
        features: string[];
    };
    plans: PlanCard[];
    business: { id: number; name: string };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;
    const currentPlan = plans.find((plan) => plan.key === license.plan);

    return (
        <>
            <Head title="License settings" />

            <h1 className="sr-only">License settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="License"
                    description={`Manage the trial and activation for ${business.name} on this machine.`}
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
                            The app is read-only. Activate an online or offline
                            license below to restore write access.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {license.is_trial && !license.is_expired ? (
                    <Alert>
                        <KeyRound className="size-4" />
                        <AlertTitle>30-day trial</AlertTitle>
                        <AlertDescription>
                            {license.days_remaining === null
                                ? 'Your trial is active.'
                                : `${license.days_remaining} day${license.days_remaining === 1 ? '' : 's'} remaining.`}{' '}
                            Activate a license anytime to keep full access after
                            the trial ends.
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
                                    .map(
                                        (feature) =>
                                            FEATURE_LABELS[feature] ?? feature,
                                    )
                                    .join(', ')}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Online activation"
                        description="Paste a license key purchased for this installation. Validation uses the local signing secret, or your configured license server."
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
                                        License key
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
                        description="Send your Machine ID to your license provider, then paste the machine-bound activation code they return."
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
