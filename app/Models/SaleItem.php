<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'sale_id',
        'product_id',
        'product_name',
        'sku',
        'quantity',
        'returned_quantity',
        'unit_price',
        'list_unit_price',
        'unit_cost',
        'negotiated_difference',
        'profit',
        'margin_bps',
        'manager_approved',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_price' => 'integer',
            'list_unit_price' => 'integer',
            'unit_cost' => 'integer',
            'negotiated_difference' => 'integer',
            'profit' => 'integer',
            'margin_bps' => 'integer',
            'manager_approved' => 'boolean',
            'line_total' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function returnableQuantity(): int
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }
}
