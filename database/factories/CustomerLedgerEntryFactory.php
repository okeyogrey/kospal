<?php

namespace Database\Factories;

use App\Enums\CustomerLedgerEntryType;
use App\Enums\LedgerDirection;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLedgerEntry>
 */
class CustomerLedgerEntryFactory extends Factory
{
    protected $model = CustomerLedgerEntry::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 50000);

        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'type' => CustomerLedgerEntryType::Sale,
            'direction' => LedgerDirection::Debit,
            'amount' => $amount,
            'balance_after' => $amount,
            'description' => fake()->sentence(),
            'reference_type' => null,
            'reference_id' => null,
            'entry_date' => now()->toDateString(),
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
