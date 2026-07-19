<?php

namespace App\Enums;

enum OperatingMode: string
{
    case AlwaysOpen = 'always_open';
    case Daytime = 'daytime';

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
            self::AlwaysOpen => '24/7',
            self::Daytime => 'Daytime',
        };
    }
}
