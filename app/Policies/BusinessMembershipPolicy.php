<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class BusinessMembershipPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantRoleIn([BusinessRole::Owner, BusinessRole::Manager]);
    }

    public function invite(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BusinessMembership $membership): bool
    {
        if (! $this->sameBusiness($membership) || ! $this->viewAny($user)) {
            return false;
        }

        if ($membership->role === BusinessRole::Owner) {
            return false;
        }

        $actorRole = $this->tenant->role();

        if ($actorRole === BusinessRole::Manager
            && ! in_array($membership->role, BusinessRole::assignableByManager(), true)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, BusinessMembership $membership): bool
    {
        return $this->update($user, $membership);
    }

    /**
     * @param  list<BusinessRole>  $roles
     */
    protected function tenantRoleIn(array $roles): bool
    {
        $role = $this->tenant->role();

        return $role !== null && in_array($role, $roles, true);
    }

    protected function sameBusiness(BusinessMembership $membership): bool
    {
        return $this->tenant->businessId() !== null
            && $membership->business_id === $this->tenant->businessId();
    }
}
