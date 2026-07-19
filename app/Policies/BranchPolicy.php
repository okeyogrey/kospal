<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class BranchPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->sameBusiness($branch)
            && $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function create(User $user): bool
    {
        return $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->sameBusiness($branch)
            && $this->tenantRoleIn([BusinessRole::Owner]);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    /**
     * @param  list<BusinessRole>  $roles
     */
    protected function tenantRoleIn(array $roles): bool
    {
        $role = $this->tenant->role();

        return $role !== null && in_array($role, $roles, true);
    }

    protected function sameBusiness(Branch $branch): bool
    {
        return $this->tenant->businessId() !== null
            && $branch->business_id === $this->tenant->businessId();
    }
}
