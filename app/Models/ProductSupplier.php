<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    use BelongsToBusiness;

    protected $table = 'product_supplier';

    protected $fillable = [
        'business_id',
        'product_id',
        'supplier_id',
        'supplier_sku',
        'cost_price',
        'is_preferred',
        'public_uuid',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
