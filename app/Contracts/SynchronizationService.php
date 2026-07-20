<?php

namespace App\Contracts;

/**
 * Optional multi-device / cloud synchronization boundary.
 *
 * Domain mutation services must not call sync directly inside retail
 * transactions; orchestration belongs at the deployment edge.
 */
interface SynchronizationService
{
    public function isEnabled(): bool;

    /**
     * @return array{status: string, last_synced_at: string|null, message: string|null}
     */
    public function status(): array;

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function push(): array;

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function pull(): array;
}
