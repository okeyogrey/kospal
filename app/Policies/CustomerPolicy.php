<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSales;
use App\Support\Tenancy\TenantContext;

class CustomerPolicy
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

    public function view(User $user, Customer $customer): bool
    {
        return $this->canAccessSales() && $this->sameBusiness($customer->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canCreateCustomers();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canManageCustomers() && $this->sameBusiness($customer->business_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canManageCustomers() && $this->sameBusiness($customer->business_id);
    }
}
