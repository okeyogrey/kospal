<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncDeferredOperation extends Model
{
    protected $fillable = [
        'sync_link_id',
        'server_operation_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'server_operation_id' => 'integer',
            'body' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SyncLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(SyncLink::class, 'sync_link_id');
    }
}
