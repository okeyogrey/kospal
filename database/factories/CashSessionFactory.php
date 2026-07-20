<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashSession;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'staff_shift_id' => StaffShift::factory(),
            'user_id' => User::factory(),
            'status' => CashSessionStatus::Open,
            'opening_float' => 50000,
            'opening_notes' => null,
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => CashSessionStatus::Closed,
            'expected_cash' => 75000,
            'counted_cash' => 75000,
            'closing_float_left' => 50000,
            'variance' => 0,
            'closed_at' => now(),
        ]);
    }
}
