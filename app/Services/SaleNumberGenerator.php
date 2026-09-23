<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SaleSequence;
use App\Models\SyncLink;

class SaleNumberGenerator
{
    /**
     * Atomically allocate the next sale number for a business.
     * Must be called inside a database transaction.
     */
    public function next(Business $business): string
    {
        $sequence = $this->lockSequence($business);
        $next = $sequence->last_number + 1;
        $sequence->update(['last_number' => $next]);

        return 'SAL-'.$this->devicePrefix($business).str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Atomically allocate the next return number for a business.
     * Must be called inside a database transaction.
     */
    public function nextReturn(Business $business): string
    {
        $sequence = $this->lockSequence($business);
        $next = $sequence->last_return_number + 1;
        $sequence->update(['last_return_number' => $next]);

        return 'RET-'.$this->devicePrefix($business).str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    protected function lockSequence(Business $business): SaleSequence
    {
        $sequence = SaleSequence::query()
            ->where('business_id', $business->id)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            SaleSequence::query()->create([
                'business_id' => $business->id,
                'last_number' => 0,
                'last_return_number' => 0,
            ]);

            $sequence = SaleSequence::query()
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $sequence;
    }

    /**
     * Linked computers share one sale-number sequence space.
     * A short device tag keeps two tills from issuing the same number.
     */
    private function devicePrefix(Business $business): string
    {
        $deviceUuid = SyncLink::query()->where('business_id', $business->id)->value('device_uuid');

        if (! is_string($deviceUuid) || $deviceUuid === '') {
            return '';
        }

        return strtoupper(substr(str_replace('-', '', $deviceUuid), 0, 4)).'-';
    }
}
