<?php

namespace App\Enums;

enum StockMovementType: string
{
    case StockReceipt = 'stock_receipt';
    /** @deprecated Kept for historical ledger rows only */
    case OpeningStock = 'opening_stock';
    case Sale = 'sale';
    case SaleVoid = 'sale_void';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
    case Return = 'return';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function increasesStock(): bool
    {
        return in_array($this, [
            self::StockReceipt,
            self::OpeningStock,
            self::SaleVoid,
            self::TransferIn,
            self::Return,
        ], true);
    }

    public function decreasesStock(): bool
    {
        return in_array($this, [
            self::Sale,
            self::TransferOut,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::StockReceipt, self::OpeningStock => 'Stock received',
            self::Sale => 'Sale',
            self::SaleVoid => 'Sale void',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
        };
    }
}
