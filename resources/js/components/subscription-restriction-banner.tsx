import { Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { edit as licenseEdit } from '@/routes/license';
import { index as subscriptionIndex } from '@/routes/subscription';

const MESSAGES: Record<string, { title: string; body: string }> = {
    trial: {
        title: 'Trial ending soon',
        body: 'Your trial no longer allows write actions. Activate a license to continue selling and updating stock.',
    },
    pending: {
        title: 'Subscription pending approval',
        body: 'You can browse existing data. Pay using the subscription instructions, then submit your transaction code for review.',
    },
    expired: {
        title: 'License expired',
        body: 'Your data remains visible. Activate or renew a license to restore sales, stock changes, and other write actions.',
    },
    suspended: {
        title: 'License suspended',
        body: 'Operational actions are blocked. Activate a valid license or contact support.',
    },
};

export function SubscriptionRestrictionBanner() {
    const { workspace, auth, deployment } = usePage().props;
    const business = workspace.business;
    const isDesktop = Boolean(
        (deployment as { is_desktop?: boolean } | undefined)?.is_desktop,
    );

    if (
        !business ||
        business.allows_write_access ||
        auth.role === 'platform_super_admin'
    ) {
        return null;
    }

    const message =
        MESSAGES[business.subscription_status] ?? MESSAGES.suspended;

    const href = isDesktop ? licenseEdit() : subscriptionIndex();
    const cta = isDesktop ? 'Open license settings' : 'Open subscription';
    const ownerHint = isDesktop
        ? 'Ask your business owner to activate a license.'
        : 'Ask your business owner to renew the subscription.';

    return (
        <div className="px-4 pt-4 md:px-6">
            <Alert>
                <Lock className="size-4" />
                <AlertTitle>{message.title}</AlertTitle>
                <AlertDescription>
                    {message.body}{' '}
                    {auth.role === 'owner' ? (
                        <Link href={href} className="font-medium underline">
                            {cta}
                        </Link>
                    ) : (
                        ownerHint
                    )}
                </AlertDescription>
            </Alert>
        </div>
    );
}
