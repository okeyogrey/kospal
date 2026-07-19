<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case Loss = 'loss';
    case Theft = 'theft';
    case Other = 'other';

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
            self::Loss => 'Loss',
            self::Theft => 'Theft',
            self::Other => 'Other',
        };
    }

    /**
     * Reasons that represent inventory losses for analytics.
     *
     * @return list<self>
     */
    public static function lossReasons(): array
    {
        return [self::Loss, self::Theft, self::Other];
    }
}
