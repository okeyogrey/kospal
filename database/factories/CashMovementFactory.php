<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'cash_session_id' => CashSession::factory(),
            'type' => CashMovementType::PaidIn,
            'amount' => 10000,
            'reason' => 'Petty cash top-up',
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
