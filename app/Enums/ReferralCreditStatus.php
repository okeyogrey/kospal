<?php

namespace App\Enums;

enum ReferralCreditStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Consumed = 'consumed';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
