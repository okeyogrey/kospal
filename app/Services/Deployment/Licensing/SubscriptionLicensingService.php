<?php

namespace App\Services\Deployment\Licensing;

use App\Contracts\LicensingService;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Services\SubscriptionService;

/**
 * SaaS subscription-backed licensing (offline payment approval model).
 *
 * Desktop builds can bind an alternate LicensingService without changing
 * write-gate middleware or Inertia shared props.
 */
class SubscriptionLicensingService implements LicensingService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
    ) {}

    public function allowsWriteAccess(Business $business): bool
    {
        return $business->allowsWriteAccess();
    }

    public function refreshStatus(Business $business): void
    {
        $this->subscriptions->expireIfPastDue($business);
    }

    public function plan(Business $business): Plan
    {
        return $business->plan;
    }

    public function status(Business $business): SubscriptionStatus
    {
        return $business->subscription_status;
    }

    public function restrictionMessage(Business $business): string
    {
        return match ($business->subscription_status->value) {
            'pending' => 'Your subscription is pending approval. Submit a payment transaction code on the Subscription page, then wait for platform review.',
            'expired' => 'Your subscription has expired. Submit a new payment on the Subscription page to restore write access.',
            'suspended' => 'Your subscription is suspended. Contact support or submit a new payment request from the Subscription page.',
            default => 'Your subscription does not allow this action. Visit the Subscription page for next steps.',
        };
    }
}
