<?php

namespace App\Support\Analytics;

use App\Contracts\DatabaseRuntime;

/**
 * Analytics period SQL helpers — delegates dialect concerns to DatabaseRuntime.
 */
final class DateGrouping
{
    /**
     * SQL expression that yields a sortable period key (YYYY-MM-DD / YYYY-Www / YYYY-MM)
     * in the given business timezone (stored timestamps are UTC).
     */
    public static function expression(string $column, string $grain = 'day', ?string $timezone = null): string
    {
        return app(DatabaseRuntime::class)->periodExpression($column, $grain, $timezone);
    }
}
