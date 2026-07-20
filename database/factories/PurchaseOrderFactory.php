<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'expected_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Draft]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Sent,
            'sent_at' => now(),
            'sent_by' => User::factory(),
        ]);
    }
}
