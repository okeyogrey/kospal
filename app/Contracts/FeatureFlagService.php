<?php

namespace App\Contracts;

use App\Models\Business;

/**
 * Feature and capacity gates for a business.
 *
 * Implementations may read plan config (SaaS) or a desktop license edition.
 * Domain services and controllers should depend on this contract, not on
 * deployment-specific plan/subscription tables directly.
 */
interface FeatureFlagService
{
    public function maxBranches(Business $business): int;

    public function maxStaff(Business $business): ?int;

    public function activeBranchCount(Business $business): int;

    public function staffSeatCount(Business $business): int;

    public function canAddBranch(Business $business): bool;

    public function canAddStaff(Business $business): bool;

    public function assertCanAddBranch(Business $business): void;

    public function assertCanAddStaff(Business $business): void;

    public function hasFeature(Business $business, string $feature): bool;

    public function assertHasFeature(Business $business, string $feature): void;

    /**
     * @return list<string>
     */
    public function features(Business $business): array;

    /**
     * Shared Inertia / UI payload for workspace limits.
     *
     * @return array{
     *     max_branches: int,
     *     active_branches: int,
     *     max_staff: int|null,
     *     staff_seats: int,
     *     features: list<string>
     * }
     */
    public function limitsPayload(Business $business): array;
}
