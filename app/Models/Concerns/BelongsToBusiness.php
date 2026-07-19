<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @mixin Model
 *
 * @property int $business_id
 */
trait BelongsToBusiness
{
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBusiness(Builder $query, Business|int $business): Builder
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return $query->where($query->getModel()->getTable().'.business_id', $businessId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $businessId = app(TenantContext::class)->businessId();

        if ($businessId === null) {
            throw new LogicException('Cannot scope to current tenant without an established business context.');
        }

        return $query->forBusiness($businessId);
    }
}
