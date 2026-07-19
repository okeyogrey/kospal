<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Models\Business;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionRequest>
 */
class SubscriptionRequestFactory extends Factory
{
    protected $model = SubscriptionRequest::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'requested_plan' => Plan::Pro,
            'current_plan' => Plan::Starter,
            'status' => SubscriptionRequestStatus::Pending,
            'notes' => null,
            'transaction_code' => strtoupper(fake()->bothify('TXN#######')),
            'reviewer_notes' => null,
            'requested_by_user_id' => User::factory(),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionRequestStatus::Approved,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
            'reviewer_notes' => 'Approved after payment verification.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionRequestStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
            'reviewer_notes' => 'Could not verify transaction code.',
        ]);
    }
}
