<?php

namespace App\Services\Deployment\Database;

use App\Contracts\BackupService;
use App\Contracts\DatabaseRuntime;
use App\Contracts\DatabaseToolkit;
use App\Contracts\DesktopSettings;
use App\Support\Database\SchemaVersion;
use App\Support\Deployment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Cross-engine database maintenance with SQLite-first desktop defaults.
 *
 * Migrations stay Laravel-portable so the same files apply to MySQL/pgsql.
 */
class LaravelDatabaseToolkit implements DatabaseToolkit
{
    public function __construct(
        protected DatabaseRuntime $runtime,
        protected BackupService $backups,
        protected DesktopSettings $settings,
    ) {}

    public function supportedDrivers(): array
    {
        /** @var list<string> $drivers */
        $drivers = config('deployment.database.supported_drivers', ['sqlite', 'mysql', 'mariadb', 'pgsql']);

        return $drivers;
    }

    public function ensureReady(): void
    {
        if (Deployment::isDesktop() && $this->runtime->isSqlite()) {
            $this->ensureSqliteFile();
        }

        $this->optimizeConnection();
    }

    public function optimizeConnection(): void
    {
        if (! $this->runtime->isSqlite()) {
            return;
        }

        $connection = $this->runtime->connectionName();
        $db = DB::connection($connection);
        $pdo = $db->getPdo();

        $busyTimeout = (int) config('deployment.database.sqlite.busy_timeout', 5000);
        $journalMode = (string) config('deployment.database.sqlite.journal_mode', 'WAL');
        $synchronous = (string) config('deployment.database.sqlite.synchronous', 'NORMAL');
        $foreignKeys = (bool) config('deployment.database.sqlite.foreign_keys', true);

        if ($busyTimeout > 0) {
            $pdo->exec("PRAGMA busy_timeout = {$busyTimeout}");
        }

        $pdo->exec('PRAGMA foreign_keys = '.($foreignKeys ? 'ON' : 'OFF'));

        // journal_mode / synchronous / temp_store cannot change inside an open transaction (e.g. tests).
        if ($db->transactionLevel() > 0) {
            return;
        }

        $pdo->exec('PRAGMA temp_store = MEMORY');

        if ($journalMode !== '') {
            $pdo->exec('PRAGMA journal_mode = '.preg_replace('/[^A-Za-z0-9_]/', '', $journalMode));
        }

        if ($synchronous !== '') {
            $pdo->exec('PRAGMA synchronous = '.preg_replace('/[^A-Za-z0-9_]/', '', $synchronous));
        }
    }

    public function migrate(bool $seed = false): array
    {
        $this->ensureReady();

        $before = $this->pendingMigrationNames();

        $params = ['--force' => true];
        if ($seed) {
            $params['--seed'] = true;
        }

        Artisan::call('migrate', $params);

        $ran = array_values(array_diff($before, $this->pendingMigrationNames()));

        if ($ran !== [] || SchemaVersion::current() !== SchemaVersion::expected()) {
            $this->recordSchemaVersion(
                $ran === []
                    ? 'migrate: schema version confirmed'
                    : 'migrate: '.implode(', ', $ran),
            );
        }

        return [
            'ran' => $ran,
            'schema_version' => SchemaVersion::expected(),
        ];
    }

    public function diagnose(): array
    {
        $issues = [];
        $driver = $this->runtime->driver();
        $connection = $this->runtime->connectionName();

        if (! in_array($driver, $this->supportedDrivers(), true)) {
            $issues[] = "Unsupported database driver [{$driver}].";
        }

        if (Deployment::isDesktop() && $driver !== 'sqlite') {
            $issues[] = 'Desktop mode normally uses SQLite; current driver is '.$driver.'.';
        }

        $pending = $this->pendingMigrationNames();
        $ranCount = $this->ranMigrationCount();

        if ($pending !== []) {
            $issues[] = count($pending).' pending migration(s).';
        }

        $schemaVersion = SchemaVersion::current();
        $expected = SchemaVersion::expected();

        if ($schemaVersion === null && Schema::hasTable(SchemaVersion::TABLE) === false) {
            $issues[] = 'Schema version table is missing (run migrations).';
        } elseif ($schemaVersion !== null && $schemaVersion < $expected) {
            $issues[] = "Schema version {$schemaVersion} is behind expected {$expected}.";
        }

        $integrity = $this->checkIntegrity();
        if (! $integrity['ok']) {
            $issues[] = 'Integrity check reported problems.';
        }

        $foreignKeys = $this->checkForeignKeys();
        if (! $foreignKeys['ok']) {
            $issues[] = 'Foreign key violations detected.';
        }

        $path = $this->runtime->databasePath();
        $databaseLabel = $path ?? (string) config('database.connections.'.$connection.'.database');

        return [
            'ok' => $issues === [],
            'driver' => $driver,
            'connection' => $connection,
            'database' => $databaseLabel !== '' ? $databaseLabel : null,
            'schema_version' => $schemaVersion,
            'expected_schema_version' => $expected,
            'migrations' => [
                'ran' => $ranCount,
                'pending' => $pending,
            ],
            'integrity' => $integrity,
            'foreign_keys' => $foreignKeys,
            'tables' => $this->tableStats(),
            'size_bytes' => $this->databaseSizeBytes(),
            'issues' => $issues,
        ];
    }

    public function repair(array $options = []): array
    {
        $vacuum = (bool) ($options['vacuum'] ?? true);
        $reindex = (bool) ($options['reindex'] ?? true);
        $backupFirst = (bool) ($options['backup_first'] ?? true);

        $actions = [];
        $messages = [];

        $this->ensureReady();

        if ($backupFirst && $this->backups->isSupported()) {
            $backup = $this->backups->create('pre-repair');
            $actions[] = 'backup:'.$backup['id'];
            $messages[] = 'Created backup '.$backup['id'];
        }

        $integrity = $this->checkIntegrity();
        if (! $integrity['ok']) {
            $messages = array_merge($messages, $integrity['messages']);
        }

        if ($this->runtime->isSqlite()) {
            $connection = DB::connection($this->runtime->connectionName());
            $pdo = $connection->getPdo();
            $inTransaction = $connection->transactionLevel() > 0;

            if ($reindex && ! $inTransaction) {
                $pdo->exec('REINDEX');
                $actions[] = 'reindex';
                $messages[] = 'REINDEX completed.';
            } elseif ($reindex) {
                $messages[] = 'Skipped REINDEX while a transaction is open.';
            }

            if ($vacuum && ! $inTransaction) {
                $pdo->exec('VACUUM');
                $actions[] = 'vacuum';
                $messages[] = 'VACUUM completed.';
            } elseif ($vacuum) {
                $messages[] = 'Skipped VACUUM while a transaction is open.';
            }

            $this->optimizeConnection();
            $actions[] = 'optimize';
            $messages[] = 'SQLite pragmas reapplied.';
        } else {
            $messages[] = 'Non-SQLite repair is limited to diagnostics and pending migrations; engine-native tools may still be required.';
        }

        if ($this->pendingMigrationNames() !== []) {
            $result = $this->migrate();
            $actions[] = 'migrate';
            $messages[] = $result['ran'] === []
                ? 'No migrations applied during repair.'
                : 'Applied migrations: '.implode(', ', $result['ran']);
        } else {
            $this->recordSchemaVersion('repair: schema version confirmed');
            $actions[] = 'schema_version';
        }

        $after = $this->diagnose();

        return [
            'ok' => $after['ok'],
            'actions' => $actions,
            'messages' => $messages,
        ];
    }

    public function exportSql(?string $path = null): array
    {
        $this->ensureReady();

        $dir = $this->exportDirectory();
        File::ensureDirectoryExists($dir);

        $path ??= $dir.DIRECTORY_SEPARATOR.sprintf(
            'kospal-export-%s.sql',
            now()->format('Ymd-His'),
        );

        $tables = $this->listUserTables();
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open export file for writing.');
        }

        try {
            fwrite($handle, "-- KOSPAL portable SQL export\n");
            fwrite($handle, '-- Generated: '.now()->toIso8601String()."\n");
            fwrite($handle, '-- Source driver: '.$this->runtime->driver()."\n");
            fwrite($handle, "-- Target: MySQL / PostgreSQL compatible INSERT dump (review types before import)\n");
            fwrite($handle, 'SET FOREIGN_KEY_CHECKS=0;'."\n\n");

            foreach ($tables as $table) {
                $this->exportTable($handle, $table);
            }

            fwrite($handle, 'SET FOREIGN_KEY_CHECKS=1;'."\n");
        } finally {
            fclose($handle);
        }

        return [
            'path' => $path,
            'tables' => count($tables),
            'bytes' => File::size($path),
        ];
    }

    public function currentSchemaVersion(): ?int
    {
        return SchemaVersion::current();
    }

    public function expectedSchemaVersion(): int
    {
        return SchemaVersion::expected();
    }

    public function recordSchemaVersion(?string $notes = null): void
    {
        SchemaVersion::record($notes, $this->runtime->driver());
    }

    protected function ensureSqliteFile(): void
    {
        $path = $this->resolveSqlitePath();

        if ($path === null || $path === ':memory:') {
            return;
        }

        config([
            'database.connections.'.$this->runtime->connectionName().'.database' => $path,
        ]);

        DB::purge($this->runtime->connectionName());

        if (! File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, '');
        }
    }

    protected function resolveSqlitePath(): ?string
    {
        $configured = $this->runtime->databasePath()
            ?? config('database.connections.'.$this->runtime->connectionName().'.database');

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        if ($configured === ':memory:') {
            return $configured;
        }

        $dataDirectory = $this->settings->get('data_directory');

        if (is_string($dataDirectory) && $dataDirectory !== '' && ! str_contains($configured, DIRECTORY_SEPARATOR) && ! str_contains($configured, '/')) {
            return rtrim($dataDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$configured;
        }

        if (is_string($dataDirectory) && $dataDirectory !== '' && Deployment::isDesktop()) {
            $preferred = rtrim($dataDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database.sqlite';

            // Prefer an existing configured path; otherwise use the desktop data directory.
            if (! File::exists($configured) && env('DB_DATABASE') === null) {
                return $preferred;
            }
        }

        return $configured;
    }

    /**
     * @return list<string>
     */
    protected function pendingMigrationNames(): array
    {
        try {
            $files = collect(File::files(database_path('migrations')))
                ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
                ->sort()
                ->values();

            $ran = Schema::hasTable('migrations')
                ? DB::table('migrations')->pluck('migration')->all()
                : [];

            return $files
                ->reject(fn (string $name) => in_array($name, $ran, true))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function ranMigrationCount(): int
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return 0;
            }

            return (int) DB::table('migrations')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{ok: bool, messages: list<string>}
     */
    protected function checkIntegrity(): array
    {
        if (! $this->runtime->isSqlite()) {
            return [
                'ok' => true,
                'messages' => ['Integrity check skipped for non-SQLite drivers.'],
            ];
        }

        try {
            $rows = DB::select('PRAGMA integrity_check');
            $messages = array_map(
                fn ($row) => (string) (is_object($row) ? ($row->integrity_check ?? reset($row)) : reset((array) $row)),
                $rows,
            );

            $ok = count($messages) === 1 && strtolower($messages[0]) === 'ok';

            return [
                'ok' => $ok,
                'messages' => $messages,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'messages' => [$e->getMessage()],
            ];
        }
    }

    /**
     * @return array{ok: bool, violations: list<string>}
     */
    protected function checkForeignKeys(): array
    {
        if (! $this->runtime->isSqlite()) {
            return [
                'ok' => true,
                'violations' => [],
            ];
        }

        try {
            $rows = DB::select('PRAGMA foreign_key_check');
            $violations = array_map(function ($row): string {
                $data = (array) $row;

                return sprintf(
                    '%s rowid=%s parent=%s fkid=%s',
                    $data['table'] ?? '?',
                    $data['rowid'] ?? '?',
                    $data['parent'] ?? '?',
                    $data['fkid'] ?? '?',
                );
            }, $rows);

            return [
                'ok' => $violations === [],
                'violations' => $violations,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'violations' => [$e->getMessage()],
            ];
        }
    }

    /**
     * @return list<array{name: string, rows: int|null}>
     */
    protected function tableStats(): array
    {
        $tables = $this->listUserTables();
        $stats = [];

        foreach ($tables as $table) {
            try {
                $stats[] = [
                    'name' => $table,
                    'rows' => (int) DB::table($table)->count(),
                ];
            } catch (Throwable) {
                $stats[] = [
                    'name' => $table,
                    'rows' => null,
                ];
            }
        }

        return $stats;
    }

    protected function databaseSizeBytes(): ?int
    {
        $path = $this->runtime->databasePath();

        if ($path !== null && File::exists($path)) {
            return File::size($path);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function listUserTables(): array
    {
        $driver = $this->runtime->driver();

        try {
            $tables = match ($driver) {
                'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                    ->pluck('name')
                    ->all(),
                'pgsql' => collect(DB::select("SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = 'public'"))
                    ->pluck('name')
                    ->all(),
                default => collect(DB::select('SHOW TABLES'))
                    ->map(fn ($row) => (string) array_values((array) $row)[0])
                    ->all(),
            };
        } catch (Throwable) {
            return [];
        }

        sort($tables);

        return array_values(array_filter(
            $tables,
            fn (string $table) => ! in_array($table, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'], true),
        ));
    }

    /**
     * @param  resource  $handle
     */
    protected function exportTable($handle, string $table): void
    {
        fwrite($handle, "-- Table: {$table}\n");

        $columns = Schema::getColumnListing($table);
        $query = DB::table($table);

        $writeRow = function (object $row) use ($handle, $table): void {
            $data = (array) $row;
            $columnSql = array_map(fn ($column) => '`'.$column.'`', array_keys($data));
            $values = array_map(fn ($value) => $this->sqlLiteral($value), array_values($data));
            fwrite(
                $handle,
                sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s);\n",
                    $table,
                    implode(', ', $columnSql),
                    implode(', ', $values),
                ),
            );
        };

        if (in_array('id', $columns, true)) {
            $query->orderBy('id')->chunk(200, function ($rows) use ($writeRow): void {
                foreach ($rows as $row) {
                    $writeRow($row);
                }
            });
        } else {
            foreach ($query->cursor() as $row) {
                $writeRow($row);
            }
        }

        fwrite($handle, "\n");
    }

    protected function sqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        return "'".str_replace("'", "''", $string)."'";
    }

    protected function exportDirectory(): string
    {
        $configured = $this->settings->get('data_directory');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'exports';
        }

        return storage_path('app/exports');
    }
}
