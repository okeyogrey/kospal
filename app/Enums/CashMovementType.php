<?php

namespace App\Enums;

enum CashMovementType: string
{
    case PaidIn = 'paid_in';
    case Drop = 'drop';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PaidIn => 'Paid in',
            self::Drop => 'Cash drop',
        };
    }

    public function sign(): int
    {
        return match ($this) {
            self::PaidIn => 1,
            self::Drop => -1,
        };
    }
}
