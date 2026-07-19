<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class ProductPolicy
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

    public function view(User $user, Product $product): bool
    {
        return $this->canManageCatalog() && $this->sameBusiness($product->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
