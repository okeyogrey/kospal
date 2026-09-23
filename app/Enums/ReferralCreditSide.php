<?php

namespace App\Enums;

enum ReferralCreditSide: string
{
    case Referrer = 'referrer';
    case Referred = 'referred';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
