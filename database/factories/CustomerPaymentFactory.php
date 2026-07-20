<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\CustomerPayment;
use App\Models\Customer;
use App\Models\User;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'customer_id' => Customer::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1000, 50000),
            'external_reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
            'paid_at' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
