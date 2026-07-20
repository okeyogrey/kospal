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
     * @return array{available: bool, version: string|null, notes: string|null}|null
     */
    public function check(): ?array;
}
