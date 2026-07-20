<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\GoodsReceivedNoteItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteItem extends Model
{
    /** @use HasFactory<GoodsReceivedNoteItemFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'goods_received_note_id',
        'product_id',
        'purchase_order_item_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function lineTotal(): int
    {
        return $this->quantity * $this->unit_cost;
    }
}
