<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceivedNoteItem>
 */
class GoodsReceivedNoteItemFactory extends Factory
{
    protected $model = GoodsReceivedNoteItem::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'goods_received_note_id' => GoodsReceivedNote::factory(),
            'product_id' => Product::factory(),
            'purchase_order_item_id' => null,
            'quantity' => fake()->numberBetween(1, 50),
            'unit_cost' => fake()->numberBetween(100, 10000),
        ];
    }
}
