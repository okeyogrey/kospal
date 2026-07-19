<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->unique()->streetName().' Branch',
            'code' => strtoupper(fake()->bothify('BR-###')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
