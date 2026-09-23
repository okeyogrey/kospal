<?php

namespace Database\Factories;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\ReferralAccount;
use App\Models\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'referral_code_id' => ReferralCode::factory(),
            'referrer_account_id' => ReferralAccount::factory(),
            'referred_account_id' => ReferralAccount::factory(),
            'status' => ReferralStatus::Pending,
            'onboarded_at' => now(),
            'qualifies_at' => now()->addWeek(),
        ];
    }
}
