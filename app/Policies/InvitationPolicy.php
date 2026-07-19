<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class InvitationPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantRoleIn([BusinessRole::Owner, BusinessRole::Manager]);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->sameBusiness($invitation) && $this->viewAny($user);
    }

    /**
     * @param  list<BusinessRole>  $roles
     */
    protected function tenantRoleIn(array $roles): bool
    {
        $role = $this->tenant->role();

        return $role !== null && in_array($role, $roles, true);
    }

    protected function sameBusiness(Invitation $invitation): bool
    {
        return $this->tenant->businessId() !== null
            && $invitation->business_id === $this->tenant->businessId();
    }
}
