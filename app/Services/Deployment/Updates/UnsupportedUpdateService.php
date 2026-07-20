<?php

namespace App\Services\Deployment\Updates;

use App\Contracts\UpdateService;

/**
 * Web / unpackaged builds do not check for desktop updates.
 */
class UnsupportedUpdateService implements UpdateService
{
    public function isSupported(): bool
    {
        return false;
    }

    public function currentVersion(): string
    {
        return (string) config('app.version', config('deployment.version', '0.0.0'));
    }

    public function check(): ?array
    {
        return null;
    }
}
