<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Lapsed = 'lapsed';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
