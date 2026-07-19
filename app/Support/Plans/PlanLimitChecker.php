<?php

namespace App\Support\Plans;

use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invitation;

class PlanLimitChecker
{
    public function maxBranches(Business $business): int
    {
        return $business->plan->maxBranches();
    }

    public function maxStaff(Business $business): ?int
    {
        if ($business->max_staff_override !== null) {
            return $business->max_staff_override;
        }

        return $business->plan->maxStaff();
    }

    public function activeBranchCount(Business $business): int
    {
        return Branch::query()
            ->forBusiness($business)
            ->where('is_active', true)
            ->count();
    }

    public function staffSeatCount(Business $business): int
    {
        $activeMembers = BusinessMembership::query()
            ->forBusiness($business)
            ->where('is_active', true)
            ->count();

        $pendingInvites = Invitation::query()
            ->forBusiness($business)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->count();

        return $activeMembers + $pendingInvites;
    }

    public function canAddBranch(Business $business): bool
    {
        return $this->activeBranchCount($business) < $this->maxBranches($business);
    }

    public function canAddStaff(Business $business): bool
    {
        $max = $this->maxStaff($business);

        if ($max === null) {
            return true;
        }

        return $this->staffSeatCount($business) < $max;
    }

    public function assertCanAddBranch(Business $business): void
    {
        abort_unless(
            $this->canAddBranch($business),
            422,
            'This plan does not allow additional active branches.',
        );
    }

    public function assertCanAddStaff(Business $business): void
    {
        abort_unless(
            $this->canAddStaff($business),
            422,
            'This plan does not allow additional staff seats.',
        );
    }

    public function hasFeature(Business $business, string $feature): bool
    {
        return $business->plan->hasFeature($feature);
    }

    public function assertHasFeature(Business $business, string $feature): void
    {
        abort_unless(
            $this->hasFeature($business, $feature),
            422,
            'This plan does not include the requested feature.',
        );
    }
}
