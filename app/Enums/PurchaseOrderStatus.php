<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Sent, self::Cancelled], true),
            self::Sent => in_array($next, [self::PartiallyReceived, self::Received, self::Cancelled], true),
            self::PartiallyReceived => in_array($next, [self::PartiallyReceived, self::Received, self::Cancelled], true),
            self::Received, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Received, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::PartiallyReceived => 'Partially received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }
}
