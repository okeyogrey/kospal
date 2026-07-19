<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesExpenses;
use App\Support\Tenancy\TenantContext;

class AttachmentPolicy
{
    use AuthorizesExpenses;

    public function __construct(
        protected TenantContext $tenant,
    ) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function download(User $user, Attachment $attachment): bool
    {
        if (! $this->canManageExpenses() || ! $this->sameBusiness($attachment->business_id)) {
            return false;
        }

        $attachable = $attachment->attachable;

        if ($attachable instanceof Expense) {
            return $user->can('view', $attachable);
        }

        return false;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->download($user, $attachment);
    }
}
