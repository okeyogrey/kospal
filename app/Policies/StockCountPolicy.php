<?php

namespace App\Policies;

use App\Enums\StockCountStatus;
use App\Models\Branch;
use App\Models\StockCount;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class StockCountPolicy
{
    use AuthorizesCatalog;

    public function __construct(protected TenantContext $tenant) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function view(User $user, StockCount $count): bool
    {
        $count->loadMissing('branch');

        return $this->canManageCatalog()
            && $this->sameBusiness($count->business_id)
            && $this->canAccessBranch($count->branch);
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $this->canManageCatalog()) {
            return false;
        }

        return $branch === null || $this->canAccessBranch($branch);
    }

    public function update(User $user, StockCount $count): bool
    {
        return $this->view($user, $count)
            && in_array($count->status, [StockCountStatus::Draft, StockCountStatus::InProgress], true);
    }

    public function complete(User $user, StockCount $count): bool
    {
        return $this->view($user, $count) && $count->status === StockCountStatus::InProgress;
    }

    public function cancel(User $user, StockCount $count): bool
    {
        return $this->view($user, $count)
            && in_array($count->status, [StockCountStatus::Draft, StockCountStatus::InProgress], true);
    }
}
