<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSales;
use App\Support\Tenancy\TenantContext;

class SalePolicy
{
    use AuthorizesSales;

    public function __construct(
        protected TenantContext $tenant,
    ) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->canAccessSales();
    }

    public function view(User $user, Sale $sale): bool
    {
        if (! $this->canAccessSales() || ! $this->sameBusiness($sale->business_id)) {
            return false;
        }

        $sale->loadMissing('branch');

        return $sale->branch !== null && $this->canAccessBranch($sale->branch);
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $this->canAccessSales()) {
            return false;
        }

        if ($branch === null) {
            return true;
        }

        return $this->canAccessBranch($branch);
    }

    public function hold(User $user, ?Branch $branch = null): bool
    {
        return $this->create($user, $branch);
    }

    public function negotiatePrice(User $user): bool
    {
        return $this->canAccessSales();
    }

    public function applyDiscount(User $user): bool
    {
        return $this->canApplySaleDiscount();
    }

    public function returnItems(User $user, Sale $sale): bool
    {
        return $this->canAccessSales()
            && $this->sameBusiness($sale->business_id)
            && $sale->status->isCompleted()
            && $this->view($user, $sale);
    }

    public function reprint(User $user, Sale $sale): bool
    {
        return $this->view($user, $sale);
    }

    public function void(User $user, Sale $sale): bool
    {
        return $this->canVoidSale()
            && $this->sameBusiness($sale->business_id)
            && $sale->status->isCompleted()
            && $this->view($user, $sale);
    }
}
