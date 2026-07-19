<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'sale_id' => Sale::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1000, 50000),
            'reference' => null,
            'notes' => null,
            'received_by' => User::factory(),
        ];
    }
}
