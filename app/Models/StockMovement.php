<?php

namespace App\Models;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToBusiness, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'user_id',
        'type',
        'quantity_delta',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reason',
        'note',
        'reference_type',
        'reference_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reason' => StockAdjustmentReason::class,
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \RuntimeException('Stock movements are immutable.');
        });

        static::deleting(function (): never {
            throw new \RuntimeException('Stock movements are immutable.');
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
