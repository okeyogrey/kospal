<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPaymentAllocation>
 */
class CustomerPaymentAllocationFactory extends Factory
{
    protected $model = CustomerPaymentAllocation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_payment_id' => CustomerPayment::factory(),
            'sale_id' => Sale::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
        ];
    }
}
