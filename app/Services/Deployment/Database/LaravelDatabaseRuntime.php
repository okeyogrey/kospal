<?php

namespace App\Services\Deployment\Database;

use App\Contracts\DatabaseRuntime;
use App\Support\Time\BusinessClock;
use Illuminate\Support\Facades\DB;

/**
 * Laravel connection-backed database runtime (driver + analytics dialect).
 */
class LaravelDatabaseRuntime implements DatabaseRuntime
{
    public function connectionName(): string
    {
        return (string) config('database.default');
    }

    public function driver(): string
    {
        return DB::connection($this->connectionName())->getDriverName();
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    public function databasePath(): ?string
    {
        if (! $this->isSqlite()) {
            return null;
        }

        $database = config('database.connections.'.$this->connectionName().'.database');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return null;
        }

        return $database;
    }

    public function periodExpression(string $column, string $grain = 'day', ?string $timezone = null): string
    {
        $driver = $this->driver();
        $localColumn = $this->localColumn($column, $timezone);

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

    protected function localColumn(string $column, ?string $timezone): string
    {
        if ($timezone === null || $timezone === '' || $timezone === 'UTC') {
            return $column;
        }

        $timezone = BusinessClock::resolve($timezone);
        $driver = $this->driver();
        $offset = BusinessClock::utcOffset($timezone);

        return match ($driver) {
            'sqlite' => $this->sqliteShift($column, $offset),
            'pgsql' => "({$column} AT TIME ZONE 'UTC' AT TIME ZONE '{$timezone}')",
            default => "CONVERT_TZ({$column}, '+00:00', '{$offset}')",
        };
    }

    protected function sqliteShift(string $column, string $offset): string
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
