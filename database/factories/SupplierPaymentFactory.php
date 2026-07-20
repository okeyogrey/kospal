<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'supplier_id' => Supplier::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1000, 50000),
            'external_reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
            'paid_at' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
