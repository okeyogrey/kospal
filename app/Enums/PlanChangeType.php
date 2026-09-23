<?php

namespace App\Enums;

enum PlanChangeType: string
{
    case Upgrade = 'upgrade';
    case Renew = 'renew';
    case Downgrade = 'downgrade';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
