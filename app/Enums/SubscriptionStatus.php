<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsWriteAccess(): bool
    {
        return $this === self::Active || $this === self::Trial;
    }

    public function isRestricted(): bool
    {
        return ! $this->allowsWriteAccess();
    }
}
