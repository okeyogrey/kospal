<?php

namespace Database\Factories;

use App\Enums\ReferralCreditSide;
use App\Enums\ReferralCreditStatus;
use App\Models\Referral;
use App\Models\ReferralAccount;
use App\Models\ReferralCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralCredit>
 */
class ReferralCreditFactory extends Factory
{
    protected $model = ReferralCredit::class;

    public function definition(): array
    {
        return [
            'referral_id' => Referral::factory(),
            'referral_account_id' => ReferralAccount::factory(),
            'side' => ReferralCreditSide::Referrer,
            'percent' => 10,
            'remaining_percent' => 10,
            'status' => ReferralCreditStatus::Available,
            'first_payment_only' => false,
            'available_at' => now(),
        ];
    }
}
