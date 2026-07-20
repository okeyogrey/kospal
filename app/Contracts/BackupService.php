<?php

namespace App\Contracts;

/**
 * Application data backup and restore.
 *
 * No-op / unsupported until a deployment-specific implementation is bound.
 * Controllers must not embed dump/restore paths directly.
 */
interface BackupService
{
    public function isSupported(): bool;

    /**
     * @return list<array{id: string, label: string|null, created_at: string, size_bytes: int|null}>
     */
    public function list(): array;

    /**
     * @return array{id: string, path: string|null}
     */
    public function create(?string $label = null): array;

    public function restore(string $backupId): void;
}
