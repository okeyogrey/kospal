<?php

namespace App\Enums;

enum CustomerLedgerEntryType: string
{
    case Sale = 'sale';
    case Payment = 'payment';
    case Adjustment = 'adjustment';
    case ReturnCredit = 'return_credit';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Credit sale',
            self::Payment => 'Payment received',
            self::Adjustment => 'Adjustment',
            self::ReturnCredit => 'Return credit',
        };
    }
}
