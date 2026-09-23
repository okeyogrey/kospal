<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRejection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sync_account_id',
        'uuid',
        'entity_type',
        'entity_uuid',
        'reason',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
