<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCostHistory>
 */
class ProductCostHistoryFactory extends Factory
{
    protected $model = ProductCostHistory::class;

    public function definition(): array
    {
        $previous = fake()->numberBetween(100, 5000);
        $new = fake()->numberBetween(100, 5000);

        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'previous_cost' => $previous,
            'new_cost' => $new,
            'quantity_on_hand' => fake()->numberBetween(0, 100),
            'quantity_received' => fake()->numberBetween(1, 50),
            'received_unit_cost' => $new,
            'source' => 'goods_received',
            'user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
