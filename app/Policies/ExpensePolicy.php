<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesExpenses;
use App\Support\Tenancy\TenantContext;

class ExpensePolicy
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

    public function view(User $user, Expense $expense): bool
    {
        if (! $this->canLogExpenses() || ! $this->sameBusiness($expense->business_id)) {
            return false;
        }

        $expense->loadMissing('branch');

        return $expense->branch !== null && $this->canAccessBranch($expense->branch);
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $this->canLogExpenses()) {
            return false;
        }

        if ($branch === null) {
            return true;
        }

        return $this->canAccessBranch($branch);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }
}
