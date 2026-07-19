<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Business::uniqueSlugFor($name),
            'country' => fake()->randomElement(['KE', 'BI']),
            'currency' => fake()->randomElement(['KES', 'BIF', 'USD']),
            'timezone' => 'Africa/Nairobi',
            'default_locale' => 'en',
            'plan' => Plan::Starter,
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_ends_at' => now()->addYear(),
            'max_staff_override' => null,
            'owner_user_id' => User::factory(),
            'is_active' => true,
        ];
    }

    public function starter(): static
    {
        return $this->state(fn () => ['plan' => Plan::Starter]);
    }

    public function pro(): static
    {
        return $this->state(fn () => ['plan' => Plan::Pro]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => ['plan' => Plan::Enterprise]);
    }
}
