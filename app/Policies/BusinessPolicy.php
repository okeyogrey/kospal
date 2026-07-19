<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class BusinessPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function view(User $user, Business $business): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $this->membershipFor($user, $business) !== null;
    }

    public function update(User $user, Business $business): bool
    {
        return $this->hasRole($user, $business, [BusinessRole::Owner]);
    }

    public function manageSubscription(User $user, Business $business): bool
    {
        return $this->hasRole($user, $business, [BusinessRole::Owner]);
    }

    public function administerSubscription(User $user, Business $business): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function viewAuditLogs(User $user, Business $business): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $this->hasRole($user, $business, [BusinessRole::Owner])
            && $business->plan->allowsAuditLogs();
    }

    /**
     * @param  list<BusinessRole>  $roles
     */
    protected function hasRole(User $user, Business $business, array $roles): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $membership = $this->membershipFor($user, $business);

        return $membership !== null
            && $membership->is_active
            && in_array($membership->role, $roles, true);
    }

    protected function membershipFor(User $user, Business $business)
    {
        if ($this->tenant->businessId() === $business->id
            && $this->tenant->user()?->is($user)
            && $this->tenant->membership()) {
            return $this->tenant->membership();
        }

        return $user->memberships()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->first();
    }
}
