<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOutbox extends Model
{
    protected $table = 'sync_outbox';

    protected $fillable = [
        'business_id',
        'uuid',
        'entity_type',
        'entity_uuid',
        'op',
        'payload',
        'occurred_at',
        'pushed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
