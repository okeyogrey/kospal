import { Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { index as subscriptionIndex } from '@/routes/subscription';

const MESSAGES: Record<string, { title: string; body: string }> = {
    pending: {
        title: 'Subscription pending approval',
        body: 'You can browse existing data. Pay using the subscription instructions, then submit your transaction code for review.',
    },
    expired: {
        title: 'Subscription expired',
        body: 'Your data remains visible. Renew from the Subscription page to restore sales, stock changes, and other write actions.',
    },
    suspended: {
        title: 'Subscription suspended',
        body: 'Operational actions are blocked. Submit a new payment request or contact support from the Subscription page.',
    },
};

export function SubscriptionRestrictionBanner() {
    const { workspace, auth } = usePage().props;
    const business = workspace.business;

    if (
        !business ||
        business.allows_write_access ||
        auth.role === 'platform_super_admin'
    ) {
        return null;
    }

    const message =
        MESSAGES[business.subscription_status] ?? MESSAGES.suspended;

    return (
        <div className="px-4 pt-4 md:px-6">
            <Alert>
                <Lock className="size-4" />
                <AlertTitle>{message.title}</AlertTitle>
                <AlertDescription>
                    {message.body}{' '}
                    {auth.role === 'owner' ? (
                        <Link
                            href={subscriptionIndex()}
                            className="font-medium underline"
                        >
                            Open subscription
                        </Link>
                    ) : (
                        'Ask your business owner to renew the subscription.'
                    )}
                </AlertDescription>
            </Alert>
        </div>
    );
}
