<?php

namespace Database\Factories;

use App\Enums\GoodsReceivedNoteStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceivedNote>
 */
class GoodsReceivedNoteFactory extends Factory
{
    protected $model = GoodsReceivedNote::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'purchase_order_id' => null,
            'status' => GoodsReceivedNoteStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => GoodsReceivedNoteStatus::Draft]);
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => GoodsReceivedNoteStatus::Posted,
            'posted_at' => now(),
            'posted_by' => User::factory(),
        ]);
    }
}
