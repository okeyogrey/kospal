<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Enums\StaffShiftStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffShift>
 */
class StaffShiftFactory extends Factory
{
    protected $model = StaffShift::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'role_at_clock_in' => BusinessRole::Cashier,
            'status' => StaffShiftStatus::Open,
            'clocked_in_at' => now(),
            'clocked_out_at' => null,
            'clocked_out_by' => null,
            'close_reason' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => StaffShiftStatus::Closed,
            'clocked_out_at' => now(),
        ]);
    }
}
