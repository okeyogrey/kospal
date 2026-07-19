<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class SubscriptionRequestPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin()
            || $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function view(User $user, SubscriptionRequest $subscriptionRequest): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $this->tenantRoleIn([BusinessRole::Owner])
            && $this->tenant->businessId() === $subscriptionRequest->business_id;
    }

    public function create(User $user): bool
    {
        return $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function review(User $user, ?SubscriptionRequest $subscriptionRequest = null): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    /**
     * @param  list<BusinessRole>  $roles
     */
    protected function tenantRoleIn(array $roles): bool
    {
        $role = $this->tenant->role();

        return $role !== null && in_array($role, $roles, true);
    }
}
