<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class CustomerPaymentPolicy
{
    use AuthorizesCatalog;

    public function __construct(protected TenantContext $tenant) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->isFinance();
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $this->isFinance() && $this->sameBusiness($payment->business_id);
    }

    public function create(User $user): bool
    {
        return $this->isFinance();
    }

    protected function isFinance(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && in_array($role, [BusinessRole::Owner, BusinessRole::Manager], true);
    }
}
