<?php

namespace App\Enums;

enum StaffShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case ForceClosed = 'force_closed';
    case AutoClosed = 'auto_closed';

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
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::ForceClosed => 'Force closed',
            self::AutoClosed => 'Auto closed',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Open;
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
