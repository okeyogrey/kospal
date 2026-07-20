<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\BusinessMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMembership extends Model
{
    /** @use HasFactory<BusinessMembershipFactory> */
    use BelongsToBusiness, HasFactory;

    protected $hidden = [
        'approval_pin',
    ];

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'negotiation_floor_percent',
        'approval_pin',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'negotiation_floor_percent' => 'integer',
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
            'approval_pin' => 'hashed',
        ];
    }

    public function hasApprovalPin(): bool
    {
        return filled($this->approval_pin);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
