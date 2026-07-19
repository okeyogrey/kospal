<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'email',
        'role',
        'token',
        'invited_by_user_id',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_user_id',
        'branch_ids',
    ];

    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'branch_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (blank($invitation->token)) {
                $invitation->token = Str::random(64);
            }
        });
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isAcceptable(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
