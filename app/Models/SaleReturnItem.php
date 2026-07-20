<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SaleReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    /** @use HasFactory<SaleReturnItemFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
