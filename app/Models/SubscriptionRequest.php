<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SubscriptionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRequest extends Model
{
    /** @use HasFactory<SubscriptionRequestFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'requested_plan',
        'current_plan',
        'status',
        'notes',
        'transaction_code',
        'reviewer_notes',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_plan' => Plan::class,
            'current_plan' => Plan::class,
            'status' => SubscriptionRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionRequestStatus::Pending;
    }
}
