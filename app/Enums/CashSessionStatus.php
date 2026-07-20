<?php

namespace App\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
