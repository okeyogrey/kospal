<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->streetAddress(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'credit_enabled' => false,
            'credit_limit' => null,
            'payment_terms_days' => null,
        ];
    }

    public function withCredit(?int $limit = 100000): static
    {
        return $this->state(fn () => [
            'credit_enabled' => true,
            'credit_limit' => $limit,
            'payment_terms_days' => 30,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
