<?php

namespace App\Services\Deployment\Licensing;

use App\Contracts\LicensingService;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Services\LicenseService;

/**
 * Desktop licensing gate backed by LicenseService (trial, activation, expiry).
 *
 * Write access requires an active trial or activated license that has not
 * expired. Feature entitlements still flow through plan → FeatureFlagService.
 */
class LocalLicensingService implements LicensingService
{
    public function __construct(
        protected LicenseService $licenses,
    ) {}

    public function allowsWriteAccess(Business $business): bool
    {
        return $this->licenses->allowsWriteAccess($business);
    }

    public function refreshStatus(Business $business): void
    {
        $this->licenses->expireIfPastDue($business);
    }

    public function plan(Business $business): Plan
    {
        return $business->plan;
    }

    public function status(Business $business): SubscriptionStatus
    {
        $this->licenses->expireIfPastDue($business);

        return $business->fresh()->subscription_status;
    }

    public function restrictionMessage(Business $business): string
    {
        $this->licenses->expireIfPastDue($business);
        $status = $business->fresh()->subscription_status;

        return match ($status) {
            SubscriptionStatus::Expired => 'Your license has expired. The app is read-only until you activate a new license in Settings → License.',
            SubscriptionStatus::Trial => 'Your trial does not allow this action. Activate a license in Settings → License.',
            SubscriptionStatus::Suspended => 'This business license is suspended. Contact support or activate a valid license in Settings → License.',
            default => 'This business is inactive or unlicensed on this installation. Ask an owner to fix it in Settings → License.',
        };
    }
}
