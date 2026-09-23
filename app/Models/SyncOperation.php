<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sync_account_id',
        'uuid',
        'device_uuid',
        'entity_type',
        'entity_uuid',
        'op',
        'payload',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SyncAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class, 'sync_account_id');
    }
}
