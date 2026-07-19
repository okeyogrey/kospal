<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class SupplierPolicy
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

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->canManageCatalog() && $this->sameBusiness($supplier->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier);
    }
}
