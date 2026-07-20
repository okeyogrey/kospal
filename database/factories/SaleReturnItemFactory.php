<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnItem>
 */
class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(100, 5000);

        return [
            'business_id' => Business::factory(),
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
            'unit_cost' => fake()->numberBetween(50, 4000),
        ];
    }
}
