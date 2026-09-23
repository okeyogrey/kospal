<?php

namespace App\Models;

use App\Services\Sync\SyncLinkIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncLink extends Model
{
    protected $fillable = [
        'business_id',
        'server_url',
        'token',
        'device_uuid',
        'join_code',
        'last_pulled_id',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_pulled_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (SyncLink $link): void {
            app(SyncLinkIndex::class)->mark($link->business_id);
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<SyncDeferredOperation, $this>
     */
    public function deferredOperations(): HasMany
    {
        return $this->hasMany(SyncDeferredOperation::class);
    }

    public function lastSyncedIso(): ?string
    {
        $value = $this->getAttribute('last_synced_at');

        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }
}
