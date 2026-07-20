<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Held = 'held';
    case Completed = 'completed';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isHeld(): bool
    {
        return $this === self::Held;
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
