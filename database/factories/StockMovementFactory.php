<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $before = fake()->numberBetween(0, 50);
        $delta = fake()->numberBetween(1, 20);

        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'type' => StockMovementType::OpeningStock,
            'quantity_delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $before + $delta,
            'reason' => null,
            'note' => fake()->optional()->sentence(),
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
