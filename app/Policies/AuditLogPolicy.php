<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class AuditLogPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $business = $this->tenant->business();

        return $business !== null
            && $user->can('viewAuditLogs', $business);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $this->tenant->businessId() === $auditLog->business_id
            && $this->viewAny($user);
    }
}
