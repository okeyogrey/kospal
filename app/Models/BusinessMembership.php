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

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
