<?php

namespace Database\Factories;

use App\Models\ReferralAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralAccount>
 */
class ReferralAccountFactory extends Factory
{
    protected $model = ReferralAccount::class;

    public function definition(): array
    {
        return [
            'public_uuid' => (string) Str::uuid(),
            'owner_email' => fake()->unique()->safeEmail(),
            'business_name' => fake()->company(),
            'is_active' => true,
            'last_seen_at' => now(),
            'cached_available_percent' => 0,
        ];
    }
}
