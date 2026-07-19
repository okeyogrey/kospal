<?php

namespace App\Support\Money;

use InvalidArgumentException;

final class Money
{
    /**
     * ISO 4217 minor-unit exponents used by KOSPAL currencies.
     *
     * @var array<string, int>
     */
    private const EXPONENTS = [
        'KES' => 2,
        'BIF' => 0,
        'USD' => 2,
    ];

    public static function exponent(string $currency): int
    {
        $currency = strtoupper($currency);

        if (! array_key_exists($currency, self::EXPONENTS)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }

        return self::EXPONENTS[$currency];
    }

    public static function toMinor(int|float|string $amount, string $currency): int
    {
        if (is_string($amount)) {
            $amount = trim($amount);

            if ($amount === '' || ! is_numeric($amount)) {
                throw new InvalidArgumentException('Amount must be numeric.');
            }
        }

        $exponent = self::exponent($currency);
        $normalized = number_format((float) $amount, $exponent, '.', '');
        $parts = explode('.', $normalized, 2);
        $major = (int) $parts[0];
        $minorPart = $parts[1] ?? str_repeat('0', $exponent);
        $sign = $major < 0 || str_starts_with((string) $amount, '-') ? -1 : 1;
        $major = abs($major);

        return $sign * (($major * (10 ** $exponent)) + (int) $minorPart);
    }

    public static function fromMinor(int $minor, string $currency): string
    {
        $exponent = self::exponent($currency);
        $negative = $minor < 0;
        $minor = abs($minor);
        $factor = 10 ** $exponent;
        $major = intdiv($minor, $factor);
        $fraction = $minor % $factor;
        $value = $exponent === 0
            ? (string) $major
            : sprintf('%d.%0'.$exponent.'d', $major, $fraction);

        return $negative ? '-'.$value : $value;
    }

    public static function format(int $minor, string $currency, ?string $locale = null): string
    {
        $currency = strtoupper($currency);
        $exponent = self::exponent($currency);
        $amount = (float) self::fromMinor($minor, $currency);
        $locale ??= self::defaultLocaleFor($currency);

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $exponent);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $formatted = number_format($amount, $exponent, '.', ',');

        return $currency.' '.$formatted;
    }

    public static function defaultLocaleFor(string $currency): string
    {
        return match (strtoupper($currency)) {
            'KES' => 'en_KE',
            'BIF' => 'fr_BI',
            'USD' => 'en_US',
            default => 'en_US',
        };
    }
}
