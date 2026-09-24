<?php

namespace App\Contracts;

/**
 * Application update channel for packaged desktop builds.
 *
 * Web deployments typically report unsupported.
 */
interface UpdateService
{
    public function isSupported(): bool;

    public function currentVersion(): string;

    /**
     * @return array{available: bool, version: string|null, notes: string|null, url?: string|null, sha256?: string|null}|null
     */
    public function check(): ?array;

    /**
     * Download a newer desktop package so the next launch installs it.
     *
     * @return array{downloaded: bool, version: string|null, notes: string|null}
     */
    public function pull(): array;
}
