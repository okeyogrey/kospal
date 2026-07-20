<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'sale_id' => Sale::factory(),
            'branch_id' => Branch::factory(),
            'cashier_id' => User::factory(),
            'approved_by' => null,
            'return_number' => 'RET-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'currency' => 'KES',
            'total' => fake()->numberBetween(100, 10000),
            'refund_method' => PaymentMethod::Cash,
            'reason' => 'Customer return',
            'client_request_id' => fake()->uuid(),
        ];
    }
}
