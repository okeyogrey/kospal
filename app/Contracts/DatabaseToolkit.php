<?php

namespace App\Contracts;

/**
 * Database maintenance: diagnose, repair, migrate helpers, export for engine moves.
 *
 * Keeps SQLite/MySQL/pgsql operational tooling out of retail domain services.
 */
interface DatabaseToolkit
{
    /**
     * Ensure the connection is usable (create SQLite file, apply pragmas).
     */
    public function ensureReady(): void;

    /**
     * Apply driver-specific runtime optimizations (e.g. SQLite WAL).
     */
    public function optimizeConnection(): void;

    /**
     * Run pending Laravel migrations and record the app schema version.
     *
     * @return array{ran: list<string>, schema_version: int}
     */
    public function migrate(bool $seed = false): array;

    /**
     * @return array{
     *     ok: bool,
     *     driver: string,
     *     connection: string,
     *     database: string|null,
     *     schema_version: int|null,
     *     expected_schema_version: int,
     *     migrations: array{ran: int, pending: list<string>},
     *     integrity: array{ok: bool, messages: list<string>},
     *     foreign_keys: array{ok: bool, violations: list<string>},
     *     tables: list<array{name: string, rows: int|null}>,
     *     size_bytes: int|null,
     *     issues: list<string>,
     * }
     */
    public function diagnose(): array;

    /**
     * Repair the database (integrity-focused). Optionally VACUUM / REINDEX on SQLite.
     *
     * @param  array{vacuum?: bool, reindex?: bool, backup_first?: bool}  $options
     * @return array{ok: bool, actions: list<string>, messages: list<string>}
     */
    public function repair(array $options = []): array;

    /**
     * Export schema + data as portable SQL for migrating to MySQL/PostgreSQL.
     *
     * @return array{path: string, tables: int, bytes: int}
     */
    public function exportSql(?string $path = null): array;

    public function currentSchemaVersion(): ?int;

    public function expectedSchemaVersion(): int;

    public function recordSchemaVersion(?string $notes = null): void;

    /**
     * Drivers this toolkit can operate against.
     *
     * @return list<string>
     */
    public function supportedDrivers(): array;
}
