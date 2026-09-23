<?php

namespace App\Services\Deployment\Synchronization;

use App\Contracts\SynchronizationService;
use App\Models\SyncLink;
use App\Services\Sync\ShopSyncService;
use App\Support\Tenancy\TenantContext;

class CloudSynchronizationService implements SynchronizationService
{
    public function __construct(
        protected ShopSyncService $shops,
    ) {}

    public function isEnabled(): bool
    {
        return $this->link() !== null;
    }

    public function status(): array
    {
        $link = $this->link();

        if ($link === null) {
            return [
                'status' => 'disabled',
                'last_synced_at' => null,
                'message' => 'This shop is not linked to other computers yet.',
            ];
        }

        return [
            'status' => filled($link->last_error) ? 'error' : 'linked',
            'last_synced_at' => $link->lastSyncedIso(),
            'message' => $link->last_error,
        ];
    }

    public function push(): array
    {
        return $this->run();
    }

    public function pull(): array
    {
        return $this->run();
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    private function run(): array
    {
        $link = $this->link();

        if ($link === null) {
            return [
                'ok' => false,
                'message' => 'This shop is not linked to other computers yet.',
            ];
        }

        try {
            $this->shops->run($link);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'message' => null,
        ];
    }

    private function link(): ?SyncLink
    {
        $businessId = app(TenantContext::class)->businessId();

        if ($businessId === null) {
            return SyncLink::query()->orderBy('id')->first();
        }

        return SyncLink::query()->where('business_id', $businessId)->first();
    }
}
