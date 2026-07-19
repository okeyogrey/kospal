<?php

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class StockTransferPolicy
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

    public function view(User $user, StockTransfer $transfer): bool
    {
        if (! $this->canManageCatalog() || ! $this->sameBusiness($transfer->business_id)) {
            return false;
        }

        $transfer->loadMissing(['sourceBranch', 'destinationBranch']);

        return $this->canAccessEitherBranch($transfer);
    }

    public function create(User $user, ?Branch $source = null, ?Branch $destination = null): bool
    {
        if (! $this->canManageCatalog()) {
            return false;
        }

        if ($source === null || $destination === null) {
            return true;
        }

        return $this->canAccessBranch($source) && $this->canAccessBranch($destination);
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $this->canManageTransfer($transfer)
            && $transfer->status === StockTransferStatus::Draft
            && $this->canAccessBothBranches($transfer);
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $this->canManageTransfer($transfer)
            && $transfer->status === StockTransferStatus::Dispatched
            && $this->canAccessBothBranches($transfer);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->canManageTransfer($transfer)
            && $transfer->status === StockTransferStatus::Draft
            && $this->canAccessBothBranches($transfer);
    }

    protected function canManageTransfer(StockTransfer $transfer): bool
    {
        return $this->canManageCatalog() && $this->sameBusiness($transfer->business_id);
    }

    protected function canAccessBothBranches(StockTransfer $transfer): bool
    {
        $transfer->loadMissing(['sourceBranch', 'destinationBranch']);

        return $transfer->sourceBranch !== null
            && $transfer->destinationBranch !== null
            && $this->canAccessBranch($transfer->sourceBranch)
            && $this->canAccessBranch($transfer->destinationBranch);
    }

    protected function canAccessEitherBranch(StockTransfer $transfer): bool
    {
        $transfer->loadMissing(['sourceBranch', 'destinationBranch']);

        return ($transfer->sourceBranch !== null && $this->canAccessBranch($transfer->sourceBranch))
            || ($transfer->destinationBranch !== null && $this->canAccessBranch($transfer->destinationBranch));
    }
}
