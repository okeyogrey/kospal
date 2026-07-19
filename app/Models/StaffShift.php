<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Enums\StaffShiftStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\StaffShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    /** @use HasFactory<StaffShiftFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'user_id',
        'role_at_clock_in',
        'status',
        'clocked_in_at',
        'clocked_out_at',
        'clocked_out_by',
        'close_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'role_at_clock_in' => BusinessRole::class,
            'status' => StaffShiftStatus::class,
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clocked_out_by');
    }

    public function durationSeconds(): ?int
    {
        if ($this->clocked_in_at === null) {
            return null;
        }

        $end = $this->clocked_out_at ?? now();

        return max(0, $this->clocked_in_at->diffInSeconds($end));
    }
}
