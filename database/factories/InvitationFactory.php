<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => BusinessRole::Cashier,
            'token' => Str::random(64),
            'invited_by_user_id' => User::factory(),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addHours(72),
            'accepted_at' => null,
            'accepted_user_id' => null,
            'branch_ids' => [],
        ];
    }
}
