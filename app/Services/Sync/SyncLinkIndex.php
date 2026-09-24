<?php

namespace App\Services\Sync;

use App\Models\SyncLink;
use Illuminate\Support\Facades\Schema;

/**
 * Per-request cache of which businesses are linked.
 * A fresh container (each test, each PHP request) starts empty.
 */
final class SyncLinkIndex
{
    /** @var array<int, bool> */
    private array $linked = [];

    public function has(int $businessId): bool
    {
        if (! Schema::hasTable('sync_links')) {
            return false;
        }

        if (! array_key_exists($businessId, $this->linked)) {
            $this->linked[$businessId] = SyncLink::query()
                ->where('business_id', $businessId)
                ->exists();
        }

        return $this->linked[$businessId];
    }

    public function mark(int $businessId): void
    {
        $this->linked[$businessId] = true;
    }
}
