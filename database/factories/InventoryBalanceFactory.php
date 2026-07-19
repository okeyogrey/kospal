<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn () => [
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
        ]);
    }
}
