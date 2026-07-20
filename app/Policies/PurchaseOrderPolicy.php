<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class PurchaseOrderPolicy
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

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->canManageCatalog()
            && $this->sameBusiness($order->business_id)
            && $this->canAccessBranch($order->branch);
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $this->canManageCatalog()) {
            return false;
        }

        return $branch === null || $this->canAccessBranch($branch);
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $this->canManage($order) && $order->status === PurchaseOrderStatus::Draft;
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $this->canManage($order)
            && in_array($order->status, [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::PartiallyReceived,
            ], true);
    }

    protected function canManage(PurchaseOrder $order): bool
    {
        $order->loadMissing('branch');

        return $this->canManageCatalog()
            && $this->sameBusiness($order->business_id)
            && $this->canAccessBranch($order->branch);
    }
}
