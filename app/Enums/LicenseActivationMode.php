<?php

namespace App\Enums;

enum LicenseActivationMode: string
{
    case Trial = 'trial';
    case Online = 'online';
    case Offline = 'offline';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActivated(): bool
    {
        return $this === self::Online || $this === self::Offline;
    }
}
