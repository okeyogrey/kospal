<?php

namespace Database\Factories;

use App\Enums\StockCountStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'branch_id' => Branch::factory(),
            'status' => StockCountStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => StockCountStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => StockCountStatus::Completed,
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }
}
