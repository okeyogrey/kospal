<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncAccount extends Model
{
    protected $fillable = [
        'business_public_uuid',
        'business_name',
        'owner_email',
        'join_code',
        'materializes',
        'materialized_operation_id',
    ];

    protected function casts(): array
    {
        return [
            'materializes' => 'boolean',
            'materialized_operation_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<SyncDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(SyncDevice::class);
    }

    /**
     * @return HasMany<SyncOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }
}
