<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ProductPackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPack extends Model
{
    /** @use HasFactory<ProductPackFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'units_per_pack',
        'barcode',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'units_per_pack' => 'integer',
            'selling_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function effectiveSellingPrice(?int $baseSellingPrice = null): int
    {
        if ($this->selling_price !== null) {
            return (int) $this->selling_price;
        }

        $base = $baseSellingPrice ?? (int) ($this->product?->selling_price ?? 0);

        return $base * max(1, (int) $this->units_per_pack);
    }

    public function toBaseQuantity(int $packQuantity): int
    {
        return max(0, $packQuantity) * max(1, (int) $this->units_per_pack);
    }
}
