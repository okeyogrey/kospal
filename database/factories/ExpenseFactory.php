<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'created_by' => User::factory(),
            'expense_date' => fake()->date(),
            'currency' => 'KES',
            'amount' => fake()->numberBetween(100, 500_000),
            'payee' => fake()->company(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
