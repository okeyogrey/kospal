<?php

namespace App\Models;

use App\Enums\ReferralCreditSide;
use App\Enums\ReferralCreditStatus;
use Database\Factories\ReferralCreditFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCredit extends Model
{
    /** @use HasFactory<ReferralCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'referral_id',
        'referral_account_id',
        'side',
        'percent',
        'remaining_percent',
        'status',
        'first_payment_only',
        'available_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'side' => ReferralCreditSide::class,
            'status' => ReferralCreditStatus::class,
            'percent' => 'integer',
            'remaining_percent' => 'integer',
            'first_payment_only' => 'boolean',
            'available_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referral_account_id');
    }

    /**
     * @param  Builder<ReferralCredit>  $query
     * @return Builder<ReferralCredit>
     */
    public function scopeSpendable(Builder $query): Builder
    {
        return $query
            ->where('status', ReferralCreditStatus::Available)
            ->where('remaining_percent', '>', 0);
    }
}
