<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class SupplierInvoicePolicy
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

    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $this->canManageCatalog() && $this->sameBusiness($invoice->business_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog();
    }

    public function post(User $user, SupplierInvoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status === SupplierInvoiceStatus::Draft;
    }

    public function void(User $user, SupplierInvoice $invoice): bool
    {
        return $this->isFinance()
            && $this->sameBusiness($invoice->business_id)
            && $invoice->status->canTransitionTo(SupplierInvoiceStatus::Void)
            && $invoice->amount_paid === 0;
    }

    protected function isFinance(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && in_array($role, [BusinessRole::Owner, BusinessRole::Manager], true);
    }
}
