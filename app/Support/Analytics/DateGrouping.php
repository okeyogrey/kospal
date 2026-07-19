<?php

namespace App\Support\Analytics;

use App\Support\Time\BusinessClock;
use Illuminate\Support\Facades\DB;

final class DateGrouping
{
    /**
     * SQL expression that yields a sortable period key (YYYY-MM-DD / YYYY-Www / YYYY-MM)
     * in the given business timezone (stored timestamps are UTC).
     */
    public static function expression(string $column, string $grain = 'day', ?string $timezone = null): string
    {
        $driver = DB::connection()->getDriverName();
        $localColumn = self::localColumn($column, $timezone);

        return match ($grain) {
            'week' => match ($driver) {
                'sqlite' => "strftime('%Y-W%W', {$localColumn})",
                'pgsql' => "to_char(({$localColumn})::timestamp, 'IYYY-\"W\"IW')",
                default => "DATE_FORMAT({$localColumn}, '%x-W%v')",
            },
            'month' => match ($driver) {
                'sqlite' => "strftime('%Y-%m', {$localColumn})",
                'pgsql' => "to_char(({$localColumn})::timestamp, 'YYYY-MM')",
                default => "DATE_FORMAT({$localColumn}, '%Y-%m')",
            },
            default => match ($driver) {
                'sqlite' => "date({$localColumn})",
                'pgsql' => "to_char(({$localColumn})::timestamp, 'YYYY-MM-DD')",
                default => "DATE({$localColumn})",
            },
        };
    }

    protected static function localColumn(string $column, ?string $timezone): string
    {
        if ($timezone === null || $timezone === '' || $timezone === 'UTC') {
            return $column;
        }

        $timezone = BusinessClock::resolve($timezone);
        $driver = DB::connection()->getDriverName();
        $offset = BusinessClock::utcOffset($timezone);

        return match ($driver) {
            'sqlite' => self::sqliteShift($column, $offset),
            'pgsql' => "({$column} AT TIME ZONE 'UTC' AT TIME ZONE '{$timezone}')",
            default => "CONVERT_TZ({$column}, '+00:00', '{$offset}')",
        };
    }

    protected static function sqliteShift(string $column, string $offset): string
    {
        $sign = str_starts_with($offset, '-') ? '-' : '+';
        $hhmm = ltrim($offset, '+-');
        [$hours, $minutes] = array_pad(explode(':', $hhmm, 2), 2, '0');
        $modifier = sprintf('%s%d hours', $sign, (int) $hours);

        if ((int) $minutes !== 0) {
            $modifier .= sprintf(', %s%d minutes', $sign, (int) $minutes);
        }

        return "datetime({$column}, '{$modifier}')";
    }
}
