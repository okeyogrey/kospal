<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Timezone helpers for Kenya (Africa/Nairobi) and Burundi (Africa/Bujumbura).
 * App storage remains UTC; business calendars are resolved in the business timezone.
 */
final class BusinessClock
{
    public const KENYA = 'Africa/Nairobi';

    public const BURUNDI = 'Africa/Bujumbura';

    public static function resolve(?string $timezone, ?string $country = null): string
    {
        if (is_string($timezone) && $timezone !== '' && self::isValid($timezone)) {
            return $timezone;
        }

        return match (strtoupper((string) $country)) {
            'BI' => self::BURUNDI,
            default => self::KENYA,
        };
    }

    public static function isValid(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public static function now(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now(self::resolve($timezone));
    }

    /**
     * Parse a calendar date (Y-m-d) as start of day in the business timezone, returned in UTC.
     */
    public static function startOfDayUtc(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::resolve($timezone))
            ->startOfDay()
            ->utc();
    }

    /**
     * Parse a calendar date (Y-m-d) as end of day in the business timezone, returned in UTC.
     */
    public static function endOfDayUtc(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::resolve($timezone))
            ->endOfDay()
            ->utc();
    }

    public static function startOfWeekUtc(CarbonInterface $moment, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)
            ->timezone(self::resolve($timezone))
            ->startOfWeek()
            ->startOfDay()
            ->utc();
    }

    public static function startOfMonthUtc(CarbonInterface $moment, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)
            ->timezone(self::resolve($timezone))
            ->startOfMonth()
            ->startOfDay()
            ->utc();
    }

    /**
     * MySQL/SQLite-friendly fixed offset (+03:00) for CONVERT_TZ / datetime modifiers.
     */
    public static function utcOffset(string $timezone, ?CarbonInterface $at = null): string
    {
        $at ??= CarbonImmutable::now('UTC');
        $offsetSeconds = CarbonImmutable::instance($at)
            ->timezone(self::resolve($timezone))
            ->getOffset();

        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $abs = abs($offsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    public static function localeForCountry(string $country): string
    {
        return match (strtoupper($country)) {
            'BI' => 'fr',
            default => 'en',
        };
    }

    public static function assertSupportedCurrency(string $currency): void
    {
        $currency = strtoupper($currency);
        $allowed = array_keys(config('kospal.currencies', []));

        if (! in_array($currency, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }
    }
}
