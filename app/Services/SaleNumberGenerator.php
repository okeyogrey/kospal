<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SaleSequence;

class SaleNumberGenerator
{
    /**
     * Atomically allocate the next sale number for a business.
     * Must be called inside a database transaction.
     */
    public function next(Business $business): string
    {
        $sequence = SaleSequence::query()
            ->where('business_id', $business->id)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            SaleSequence::query()->create([
                'business_id' => $business->id,
                'last_number' => 0,
            ]);

            $sequence = SaleSequence::query()
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $next = $sequence->last_number + 1;

        $sequence->update(['last_number' => $next]);

        return 'SAL-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
