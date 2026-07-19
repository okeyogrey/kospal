<?php

namespace App\Policies\Concerns;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;

trait AuthorizesCatalog
{
    abstract protected function tenant(): TenantContext;

    protected function canManageCatalog(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canManageCatalog();
    }

    protected function sameBusiness(int $businessId): bool
    {
        return $this->tenant()->businessId() !== null
            && $businessId === $this->tenant()->businessId();
    }

    protected function canAccessBranch(Branch $branch): bool
    {
        if (! $this->sameBusiness($branch->business_id)) {
            return false;
        }

        $user = $this->tenant()->user();
        $membership = $this->tenant()->membership();
        $business = $this->tenant()->business();

        if ($user === null || $membership === null || $business === null) {
            return false;
        }

        if (in_array($membership->role, [BusinessRole::Owner, BusinessRole::Manager], true)) {
            return true;
        }

        return app(ResolvesTenant::class)
            ->allowedBranches($user, $membership, $business)
            ->contains('id', $branch->id);
    }
}
