<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Policies\Concerns\AuthorizesExpenses;
use App\Support\Tenancy\TenantContext;

class ExpenseCategoryPolicy
{
    use AuthorizesExpenses;

    public function __construct(
        protected TenantContext $tenant,
    ) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->canLogExpenses();
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->canLogExpenses() && $this->sameBusiness($expenseCategory->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageExpenseCategories();
    }

    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->canManageExpenseCategories() && $this->sameBusiness($expenseCategory->business_id);
    }

    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->canManageExpenseCategories() && $this->sameBusiness($expenseCategory->business_id);
    }
}
