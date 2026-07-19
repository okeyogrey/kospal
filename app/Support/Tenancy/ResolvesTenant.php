<?php

namespace App\Support\Tenancy;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolvesTenant
{
    public function __construct(
        protected TenantContext $context,
    ) {}

    public function resolve(?User $user): void
    {
        $this->context->clear();

        if ($user === null) {
            return;
        }

        if ($user->is_platform_super_admin) {
            $this->context->set($user);

            return;
        }

        $memberships = $user->memberships()
            ->with('business')
            ->where('is_active', true)
            ->get();

        if ($memberships->isEmpty()) {
            $this->context->set($user);

            return;
        }

        $membership = $this->selectMembership($user, $memberships);

        if ($membership === null) {
            $this->context->set($user);

            return;
        }

        /** @var Business $business */
        $business = $membership->business;
        $branch = $this->selectBranch($user, $membership, $business);

        if ($user->current_business_id !== $business->id || $user->current_branch_id !== $branch?->id) {
            $user->forceFill([
                'current_business_id' => $business->id,
                'current_branch_id' => $branch?->id,
            ])->saveQuietly();
        }

        $this->context->set($user, $business, $membership, $branch);
    }

    /**
     * @param  Collection<int, BusinessMembership>  $memberships
     */
    protected function selectMembership(User $user, Collection $memberships): ?BusinessMembership
    {
        if ($user->current_business_id) {
            $current = $memberships->firstWhere('business_id', $user->current_business_id);

            if ($current) {
                return $current;
            }
        }

        return $memberships->first();
    }

    protected function selectBranch(User $user, BusinessMembership $membership, Business $business): ?Branch
    {
        $allowed = $this->allowedBranches($user, $membership, $business);

        if ($allowed->isEmpty()) {
            return null;
        }

        if ($user->current_branch_id) {
            $current = $allowed->firstWhere('id', $user->current_branch_id);

            if ($current) {
                return $current;
            }
        }

        return $allowed->first();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function allowedBranches(User $user, BusinessMembership $membership, Business $business): Collection
    {
        if ($membership->role === BusinessRole::Owner) {
            return Branch::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $assigned = $user->branches()
            ->where('branches.business_id', $business->id)
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->get();

        // Managers with no branch rows keep unrestricted access to all branches.
        if ($membership->role === BusinessRole::Manager && $assigned->isEmpty()) {
            return Branch::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return $assigned;
    }
}
