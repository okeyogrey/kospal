<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class InventoryBalancePolicy
{
    use AuthorizesCatalog;

    public function __construct(
        protected TenantContext $tenant,
    ) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function view(User $user, InventoryBalance $balance): bool
    {
        if (! $this->canManageCatalog() || ! $this->sameBusiness($balance->business_id)) {
            return false;
        }

        $balance->loadMissing('branch');

        return $balance->branch !== null && $this->canAccessBranch($balance->branch);
    }

    public function receiveStock(User $user, Branch $branch): bool
    {
        return $this->canManageCatalog() && $this->canAccessBranch($branch);
    }

    /**
     * @deprecated Use receiveStock()
     */
    public function createOpeningStock(User $user, Branch $branch): bool
    {
        return $this->receiveStock($user, $branch);
    }

    public function adjust(User $user, Branch $branch): bool
    {
        return $this->canManageCatalog() && $this->canAccessBranch($branch);
    }
}
