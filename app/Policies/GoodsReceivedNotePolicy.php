<?php

namespace App\Policies;

use App\Enums\GoodsReceivedNoteStatus;
use App\Models\Branch;
use App\Models\GoodsReceivedNote;
use App\Models\User;
use App\Policies\Concerns\AuthorizesCatalog;
use App\Support\Tenancy\TenantContext;

class GoodsReceivedNotePolicy
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

    public function view(User $user, GoodsReceivedNote $grn): bool
    {
        $grn->loadMissing('branch');

        return $this->canManageCatalog()
            && $this->sameBusiness($grn->business_id)
            && $this->canAccessBranch($grn->branch);
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $this->canManageCatalog()) {
            return false;
        }

        return $branch === null || $this->canAccessBranch($branch);
    }

    public function post(User $user, GoodsReceivedNote $grn): bool
    {
        return $this->view($user, $grn) && $grn->status === GoodsReceivedNoteStatus::Draft;
    }

    public function cancel(User $user, GoodsReceivedNote $grn): bool
    {
        return $this->view($user, $grn) && $grn->status === GoodsReceivedNoteStatus::Draft;
    }
}
