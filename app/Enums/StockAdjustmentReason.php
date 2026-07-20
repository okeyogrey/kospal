<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case Loss = 'loss';
    case Theft = 'theft';
    case Damage = 'damage';
    case Found = 'found';
    case CountVariance = 'count_variance';
    case Correction = 'correction';
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
            self::Damage => 'Damage',
            self::Found => 'Found',
            self::CountVariance => 'Count variance',
            self::Correction => 'Correction',
            self::Other => 'Other',
        };
    }

    public function allowsIncrease(): bool
    {
        return in_array($this, [
            self::Found,
            self::CountVariance,
            self::Correction,
            self::Other,
        ], true);
    }

    public function allowsDecrease(): bool
    {
        return in_array($this, [
            self::Loss,
            self::Theft,
            self::Damage,
            self::CountVariance,
            self::Correction,
            self::Other,
        ], true);
    }

    /**
     * Reasons that represent inventory losses for analytics.
     *
     * @return list<self>
     */
    public static function lossReasons(): array
    {
        return [self::Loss, self::Theft, self::Damage, self::Other];
    }
}
