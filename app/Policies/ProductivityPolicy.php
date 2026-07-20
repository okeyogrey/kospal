<?php

namespace App\Policies;

use App\Contracts\FeatureFlagService;
use App\Enums\ImportEntity;
use App\Enums\SpreadsheetFormat;
use App\Models\User;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\TenantContext;

class ProductivityPolicy
{
    public function __construct(
        protected TenantContext $tenant,
        protected FeatureFlagService $features,
    ) {}

    public function viewAny(User $user): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $this->tenant->role();

        return $role !== null && in_array($role->value, ['owner', 'manager', 'inventory_clerk'], true);
    }

    public function import(User $user, ImportEntity|string $entity, SpreadsheetFormat|string $format): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $business = $this->tenant->business();
        if ($business === null) {
            return false;
        }

        $spreadsheetFormat = $format instanceof SpreadsheetFormat
            ? $format
            : SpreadsheetFormat::tryFrom((string) $format);

        if ($spreadsheetFormat === null) {
            return false;
        }

        $feature = match ($spreadsheetFormat) {
            SpreadsheetFormat::Csv => Features::CSV_IMPORT,
            SpreadsheetFormat::Xlsx => Features::EXCEL_IMPORT,
        };

        if (! $this->features->hasFeature($business, $feature)) {
            return false;
        }

        $importEntity = $entity instanceof ImportEntity
            ? $entity
            : ImportEntity::tryFrom((string) $entity);

        if ($importEntity === null) {
            return false;
        }

        $role = $this->tenant->role();

        return match ($importEntity) {
            ImportEntity::Products => $role?->canManageCatalog() ?? false,
            ImportEntity::Customers => $role?->canManageCustomers() ?? false,
        };
    }

    public function export(User $user, ImportEntity|string $entity, SpreadsheetFormat|string $format): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $business = $this->tenant->business();
        if ($business === null) {
            return false;
        }

        $spreadsheetFormat = $format instanceof SpreadsheetFormat
            ? $format
            : SpreadsheetFormat::tryFrom((string) $format);

        if ($spreadsheetFormat === null) {
            return false;
        }

        $feature = match ($spreadsheetFormat) {
            SpreadsheetFormat::Csv => Features::CSV_EXPORT,
            SpreadsheetFormat::Xlsx => Features::EXCEL_EXPORT,
        };

        if (! $this->features->hasFeature($business, $feature)) {
            return false;
        }

        $importEntity = $entity instanceof ImportEntity
            ? $entity
            : ImportEntity::tryFrom((string) $entity);

        if ($importEntity === null) {
            return false;
        }

        $role = $this->tenant->role();

        return match ($importEntity) {
            ImportEntity::Products => $role?->canManageCatalog() ?? false,
            ImportEntity::Customers => $role?->canManageCustomers() ?? false,
        };
    }
}
