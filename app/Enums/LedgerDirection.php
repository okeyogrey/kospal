<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function signedAmount(int $amount): int
    {
        return $this === self::Debit ? $amount : -$amount;
    }
}
