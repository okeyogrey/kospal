<?php

namespace App\Support\Time;

use App\Enums\OperatingMode;
use App\Models\Branch;
use App\Models\Business;
use Carbon\CarbonImmutable;

final class OperatingHours
{
    /**
     * @return array{mode: OperatingMode, opens_at: string|null, closes_at: string|null}
     */
    public static function resolve(Business $business, Branch $branch): array
    {
        $mode = $branch->operating_mode ?? $business->operating_mode ?? OperatingMode::AlwaysOpen;
        $opensAt = $branch->opens_at ?? $business->opens_at;
        $closesAt = $branch->closes_at ?? $business->closes_at;

        return [
            'mode' => $mode,
            'opens_at' => self::timeString($opensAt),
            'closes_at' => self::timeString($closesAt),
        ];
    }

    public static function isOutsideHours(Business $business, Branch $branch, ?CarbonImmutable $at = null): bool
    {
        $resolved = self::resolve($business, $branch);

        if ($resolved['mode'] !== OperatingMode::Daytime) {
            return false;
        }

        if ($resolved['opens_at'] === null || $resolved['closes_at'] === null) {
            return false;
        }

        $timezone = BusinessClock::resolve($business->timezone, $business->country);
        $local = ($at ?? CarbonImmutable::now('UTC'))->timezone($timezone);
        $current = $local->format('H:i:s');

        $opens = $resolved['opens_at'];
        $closes = $resolved['closes_at'];

        if ($opens <= $closes) {
            return $current < $opens || $current > $closes;
        }

        // Overnight daytime window (e.g. 22:00–06:00) — rare but supported.
        return $current < $opens && $current > $closes;
    }

    protected static function timeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }
}
