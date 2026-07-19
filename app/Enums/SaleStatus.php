<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isVoided(): bool
    {
        return $this === self::Voided;
    }
}
