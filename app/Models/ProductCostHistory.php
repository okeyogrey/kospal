<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ProductCostHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductCostHistory extends Model
{
    /** @use HasFactory<ProductCostHistoryFactory> */
    use BelongsToBusiness, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'product_id',
        'previous_cost',
        'new_cost',
        'quantity_on_hand',
        'quantity_received',
        'received_unit_cost',
        'source',
        'reference_type',
        'reference_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_cost' => 'integer',
            'new_cost' => 'integer',
            'quantity_on_hand' => 'integer',
            'quantity_received' => 'integer',
            'received_unit_cost' => 'integer',
            'created_at' => 'datetime',
        ];
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
