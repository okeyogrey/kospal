<?php

namespace App\Models;

use Database\Factories\ReferralAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferralAccount extends Model
{
    /** @use HasFactory<ReferralAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'public_uuid',
        'business_id',
        'owner_user_id',
        'owner_email',
        'business_name',
        'machine_id',
        'api_token_hash',
        'remote_token',
        'is_active',
        'last_seen_at',
        'first_paid_period_at',
        'cached_available_percent',
    ];

    protected function casts(): array
    {
        return [
            'remote_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'first_paid_period_at' => 'datetime',
            'cached_available_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReferralAccount $account): void {
            if (blank($account->public_uuid)) {
                $account->public_uuid = (string) Str::uuid();
            }

            $account->owner_email = self::normalizeEmail($account->owner_email);
        });
    }

    public static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ReferralCode::class);
    }

    public function referredReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referred_account_id');
    }

    public function referrerReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_account_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ReferralCredit::class);
    }
}
