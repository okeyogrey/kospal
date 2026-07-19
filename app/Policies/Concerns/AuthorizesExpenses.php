<?php

namespace App\Policies\Concerns;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;

trait AuthorizesExpenses
{
    abstract protected function tenant(): TenantContext;

    protected function canLogExpenses(): bool
    {
        $role = $this->tenant()->role();
        $business = $this->tenant()->business();

        if ($role === null || $business === null) {
            return false;
        }

        if ($role->canLogExpensesByDefault()) {
            return true;
        }

        return $role === BusinessRole::Cashier && $business->cashiers_can_log_expenses;
    }

    /**
     * @deprecated Use canLogExpenses()
     */
    protected function canManageExpenses(): bool
    {
        return $this->canLogExpenses();
    }

    protected function canManageExpenseCategories(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canManageExpenseCategories();
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
