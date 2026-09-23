<?php

namespace App\Support\Money;

use InvalidArgumentException;

final class CurrencyConverter
{
    public static function bifPerKes(): int
    {
        return max(1, (int) config('kospal.exchange.bif_per_kes', 50));
    }

    public static function kesMajorToBif(int|float|string $kes): int
    {
        return (int) round(((float) $kes) * self::bifPerKes());
    }

    public static function bifToKesMajor(int|float|string $bif): float
    {
        return ((float) $bif) / self::bifPerKes();
    }

    public static function kesMinorToBifMinor(int $kesMinor): int
    {
        $kesMajor = (float) Money::fromMinor($kesMinor, 'KES');

        return Money::toMinor(self::kesMajorToBif($kesMajor), 'BIF');
    }

    public static function bifMinorToKesMinor(int $bifMinor): int
    {
        $bifMajor = (float) Money::fromMinor($bifMinor, 'BIF');

        return Money::toMinor(self::bifToKesMajor($bifMajor), 'KES');
    }

    public static function convertMinor(int $minor, string $from, string $to): int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $minor;
        }

        if ($from === 'KES' && $to === 'BIF') {
            return self::kesMinorToBifMinor($minor);
        }

        if ($from === 'BIF' && $to === 'KES') {
            return self::bifMinorToKesMinor($minor);
        }

        throw new InvalidArgumentException("Unsupported conversion [{$from} -> {$to}].");
    }
}
