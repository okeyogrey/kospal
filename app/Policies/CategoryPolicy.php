<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class CategoryPolicy
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

    public function view(User $user, Category $category): bool
    {
        return $this->canManageCatalog() && $this->sameBusiness($category->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function update(User $user, Category $category): bool
    {
        return $this->view($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->view($user, $category);
    }
}
