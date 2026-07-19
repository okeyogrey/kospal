<?php

namespace App\Policies\Concerns;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;

trait AuthorizesSales
{
    abstract protected function tenant(): TenantContext;

    protected function canAccessSales(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canAccessSales();
    }

    protected function canApplySaleDiscount(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canApplySaleDiscount();
    }

    protected function canVoidSale(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canVoidSale();
    }

    protected function canManageCustomers(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canManageCustomers();
    }

    protected function canCreateCustomers(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canCreateCustomers();
    }

    protected function sameBusiness(?int $businessId): bool
    {
        return $businessId !== null
            && $this->tenant()->businessId() !== null
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
