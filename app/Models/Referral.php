<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory;

    protected $fillable = [
        'referral_code_id',
        'referrer_account_id',
        'referred_account_id',
        'status',
        'onboarded_at',
        'qualifies_at',
        'qualified_at',
        'voided_at',
        'void_reason',
        'voided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'onboarded_at' => 'datetime',
            'qualifies_at' => 'datetime',
            'qualified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function referrerAccount(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referrer_account_id');
    }

    public function referredAccount(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referred_account_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ReferralCredit::class);
    }
}
