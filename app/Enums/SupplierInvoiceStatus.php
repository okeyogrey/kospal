<?php

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

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
            self::Draft => in_array($next, [self::Posted, self::Void], true),
            self::Posted => in_array($next, [self::PartiallyPaid, self::Paid, self::Void], true),
            self::PartiallyPaid => in_array($next, [self::PartiallyPaid, self::Paid, self::Void], true),
            self::Paid => $next === self::Void,
            self::Void => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Posted, self::PartiallyPaid], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }
}
