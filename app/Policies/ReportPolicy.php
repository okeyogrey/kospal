<?php

namespace App\Policies;

use App\Enums\ReportType;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class ReportPolicy
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    public function viewAny(User $user): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $this->tenant->role();

        return $role !== null && $role->canViewReports();
    }

    public function view(User $user, ReportType|string $report): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $type = $report instanceof ReportType
            ? $report
            : ReportType::tryFrom((string) $report);

        if ($type === null) {
            return false;
        }

        $business = $this->tenant->business();

        if ($business === null) {
            return false;
        }

        // Locked reports are still viewable so we can render an upgrade screen.
        // Export and data access are gated separately.
        return true;
    }

    public function export(User $user, ReportType|string $report): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $business = $this->tenant->business();
        if ($business === null || ! $business->plan->allowsCsvExport()) {
            return false;
        }

        $type = $report instanceof ReportType
            ? $report
            : ReportType::tryFrom((string) $report);

        if ($type === null || ! $type->supportsCsvExport()) {
            return false;
        }

        return $type->isAvailableOn($business->plan);
    }
}
