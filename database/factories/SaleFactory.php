<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 50000);
        $discount = 0;
        $total = $subtotal - $discount;

        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'customer_id' => null,
            'cashier_id' => User::factory(),
            'sale_number' => 'SAL-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => SaleStatus::Completed,
            'payment_method' => PaymentMethod::Cash,
            'currency' => 'KES',
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => $total,
            'customer_name' => null,
            'notes' => null,
            'client_request_id' => fake()->uuid(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => SaleStatus::Voided,
            'voided_at' => now(),
            'void_reason' => 'Test void',
        ]);
    }
}
