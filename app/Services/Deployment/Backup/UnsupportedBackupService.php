<?php

namespace App\Services\Deployment\Backup;

use App\Contracts\BackupService;
use RuntimeException;

/**
 * Placeholder until a deployment-specific backup strategy is implemented.
 */
class UnsupportedBackupService implements BackupService
{
    public function isSupported(): bool
    {
        return false;
    }

    public function list(): array
    {
        return [];
    }

    public function create(?string $label = null): array
    {
        throw new RuntimeException('Backups are not configured for this deployment.');
    }

    public function restore(string $backupId): void
    {
        throw new RuntimeException('Backup restore is not configured for this deployment.');
    }
}
