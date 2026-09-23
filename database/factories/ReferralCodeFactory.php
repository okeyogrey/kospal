<?php

namespace Database\Factories;

use App\Models\ReferralAccount;
use App\Models\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    protected $model = ReferralCode::class;

    public function definition(): array
    {
        return [
            'referral_account_id' => ReferralAccount::factory(),
            'code' => 'KSP-'.Str::upper(Str::random(6)),
            'expires_at' => now()->addDays(3),
        ];
    }
}
