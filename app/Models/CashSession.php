<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'staff_shift_id',
        'user_id',
        'status',
        'opening_float',
        'opening_notes',
        'opened_at',
        'expected_cash',
        'counted_cash',
        'closing_float_left',
        'variance',
        'variance_reason',
        'variance_approved_by',
        'closed_at',
        'closed_by',
        'z_report_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_float' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'closing_float_left' => 'integer',
            'variance' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'z_report_snapshot' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staffShift(): BelongsTo
    {
        return $this->belongsTo(StaffShift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function varianceApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_approved_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
