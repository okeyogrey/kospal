<?php

namespace App\Console\Commands\Database;

use App\Contracts\DatabaseRuntime;
use App\Contracts\DatabaseToolkit;
use App\Support\Database\SchemaVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseVersionCommand extends Command
{
    protected $signature = 'db:version';

    protected $description = 'Show KOSPAL database schema version history';

    public function handle(DatabaseToolkit $toolkit, DatabaseRuntime $runtime): int
    {
        $current = $toolkit->currentSchemaVersion();
        $expected = $toolkit->expectedSchemaVersion();

        $this->table(
            ['Field', 'Value'],
            [
                ['Current schema version', $current === null ? '—' : (string) $current],
                ['Expected schema version', (string) $expected],
                ['Driver', $runtime->driver()],
                ['App version', (string) config('deployment.version')],
            ],
        );

        if (! Schema::hasTable(SchemaVersion::TABLE)) {
            $this->warn('database_versions table is missing. Run php artisan db:prepare.');

            return self::FAILURE;
        }

        $history = DB::table(SchemaVersion::TABLE)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['schema_version', 'driver', 'app_version', 'notes', 'created_at']);

        if ($history->isEmpty()) {
            $this->comment('No version history rows yet.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Recent history');
        $this->table(
            ['Version', 'Driver', 'App', 'Notes', 'Recorded'],
            $history->map(fn ($row) => [
                $row->schema_version,
                $row->driver,
                $row->app_version ?? '—',
                $row->notes ?? '—',
                (string) $row->created_at,
            ])->all(),
        );

        return $current !== null && $current >= $expected ? self::SUCCESS : self::FAILURE;
    }
}
