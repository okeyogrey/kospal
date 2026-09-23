<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncStock extends Model
{
    protected $table = 'sync_stock';

    protected $fillable = [
        'sync_account_id',
        'branch_uuid',
        'product_uuid',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
