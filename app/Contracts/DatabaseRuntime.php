<?php

namespace App\Contracts;

/**
 * Runtime database deployment concerns (driver, paths, dialect helpers).
 *
 * Keeps analytics SQL and backup tooling free of scattered driver checks.
 */
interface DatabaseRuntime
{
    public function connectionName(): string;

    public function driver(): string;

    public function isSqlite(): bool;

    /**
     * Absolute path to the SQLite database file, when applicable.
     */
    public function databasePath(): ?string;

    /**
     * SQL expression for a timezone-aware period key.
     */
    public function periodExpression(string $column, string $grain = 'day', ?string $timezone = null): string;
}
