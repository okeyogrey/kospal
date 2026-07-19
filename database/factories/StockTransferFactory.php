<?php

namespace Database\Factories;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'source_branch_id' => Branch::factory(),
            'destination_branch_id' => Branch::factory(),
            'status' => StockTransferStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => StockTransferStatus::Draft]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => [
            'status' => StockTransferStatus::Dispatched,
            'dispatched_at' => now(),
            'dispatched_by' => User::factory(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => StockTransferStatus::Received,
            'dispatched_at' => now()->subHour(),
            'dispatched_by' => User::factory(),
            'received_at' => now(),
            'received_by' => User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => StockTransferStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
        ]);
    }
}
