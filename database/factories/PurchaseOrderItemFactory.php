<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity_ordered' => fake()->numberBetween(1, 50),
            'quantity_received' => 0,
            'unit_cost' => fake()->numberBetween(100, 10000),
        ];
    }
}
