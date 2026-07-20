<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'cash_session_id',
        'type',
        'amount',
        'reason',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'integer',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
