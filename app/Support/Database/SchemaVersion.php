<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Application-level schema version tracking (alongside Laravel's migrations table).
 */
final class SchemaVersion
{
    public const TABLE = 'database_versions';

    public static function expected(): int
    {
        return (int) config('deployment.database.schema_version', 1);
    }

    public static function current(): ?int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $value = DB::table(self::TABLE)->max('schema_version');

        return $value === null ? null : (int) $value;
    }

    public static function record(?string $notes = null, ?string $driver = null): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)->insert([
            'schema_version' => self::expected(),
            'driver' => $driver ?? DB::getDriverName(),
            'app_version' => (string) config('deployment.version'),
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
