<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCountItem>
 */
class StockCountItemFactory extends Factory
{
    protected $model = StockCountItem::class;

    public function definition(): array
    {
        $system = fake()->numberBetween(0, 100);

        return [
            'business_id' => Business::factory(),
            'stock_count_id' => StockCount::factory(),
            'product_id' => Product::factory(),
            'system_quantity' => $system,
            'counted_quantity' => null,
            'variance' => null,
        ];
    }
}
