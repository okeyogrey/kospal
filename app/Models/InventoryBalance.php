<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, Branch|int $branch): Builder
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return $query->where($query->getModel()->getTable().'.branch_id', $branchId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('product', function (Builder $product): void {
            $product->whereColumn('inventory_balances.quantity', '<=', 'products.reorder_level');
        });
    }

    public function isLowStock(): bool
    {
        $product = $this->product;

        return $product !== null && $this->quantity <= $product->reorder_level;
    }

    public function inventoryValueMinor(): int
    {
        return $this->quantity * (int) ($this->product?->cost_price ?? 0);
    }
}
