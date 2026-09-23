<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncDevice extends Model
{
    protected $fillable = [
        'sync_account_id',
        'device_uuid',
        'name',
        'token_hash',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
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
