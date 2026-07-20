<?php

namespace App\Contracts;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;

/**
 * Deployment-agnostic license / entitlement gate.
 *
 * Desktop: LicenseService (trial, online/offline activation, expiry).
 * Web/SaaS: SubscriptionService (offline payment approval).
 */
interface LicensingService
{
    public function allowsWriteAccess(Business $business): bool;

    /**
     * Refresh derived license state (e.g. expire past-due subscriptions).
     */
    public function refreshStatus(Business $business): void;

    public function plan(Business $business): Plan;

    public function status(Business $business): SubscriptionStatus;

    public function restrictionMessage(Business $business): string;
}
