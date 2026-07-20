<?php

namespace App\Services\Deployment\Synchronization;

use App\Contracts\SynchronizationService;

/**
 * No-op sync adapter — retail domain stays local until sync is designed.
 */
class NoOpSynchronizationService implements SynchronizationService
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function status(): array
    {
        return [
            'status' => 'disabled',
            'last_synced_at' => null,
            'message' => 'Synchronization is not enabled for this deployment.',
        ];
    }

    public function push(): array
    {
        return [
            'ok' => false,
            'message' => 'Synchronization is not enabled for this deployment.',
        ];
    }

    public function pull(): array
    {
        return [
            'ok' => false,
            'message' => 'Synchronization is not enabled for this deployment.',
        ];
    }
}
