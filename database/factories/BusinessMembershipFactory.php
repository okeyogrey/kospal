<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMembership>
 */
class BusinessMembershipFactory extends Factory
{
    protected $model = BusinessMembership::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessRole::Cashier,
            'negotiation_floor_percent' => 100,
            'approval_pin' => null,
            'is_active' => true,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => BusinessRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => BusinessRole::Manager]);
    }

    public function cashier(): static
    {
        return $this->state(fn () => ['role' => BusinessRole::Cashier]);
    }

    public function inventoryClerk(): static
    {
        return $this->state(fn () => ['role' => BusinessRole::InventoryClerk]);
    }
}
