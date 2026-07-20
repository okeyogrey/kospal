<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\StockCountItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    /** @use HasFactory<StockCountItemFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'stock_count_id',
        'product_id',
        'system_quantity',
        'counted_quantity',
        'variance',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'variance' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
