<?php

namespace App\Console\Commands\Database;

use App\Contracts\DatabaseToolkit;
use Illuminate\Console\Command;

class DatabaseDiagnoseCommand extends Command
{
    protected $signature = 'db:diagnose {--json : Output machine-readable JSON}';

    protected $description = 'Run database diagnostics (driver, migrations, integrity, foreign keys)';

    public function handle(DatabaseToolkit $toolkit): int
    {
        $toolkit->ensureReady();
        $report = $toolkit->diagnose();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return $report['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Database diagnostics');
        $this->table(
            ['Field', 'Value'],
            [
                ['OK', $report['ok'] ? 'yes' : 'no'],
                ['Driver', $report['driver']],
                ['Connection', $report['connection']],
                ['Database', $report['database'] ?? '—'],
                ['Schema version', ($report['schema_version'] ?? '—').' / '.$report['expected_schema_version']],
                ['Migrations ran', (string) $report['migrations']['ran']],
                ['Pending', (string) count($report['migrations']['pending'])],
                ['Size (bytes)', $report['size_bytes'] === null ? '—' : (string) $report['size_bytes']],
                ['Integrity', $report['integrity']['ok'] ? 'ok' : 'failed'],
                ['Foreign keys', $report['foreign_keys']['ok'] ? 'ok' : 'violations'],
            ],
        );

        if ($report['migrations']['pending'] !== []) {
            $this->warn('Pending migrations:');
            foreach ($report['migrations']['pending'] as $migration) {
                $this->line('  - '.$migration);
            }
        }

        if ($report['issues'] !== []) {
            $this->error('Issues:');
            foreach ($report['issues'] as $issue) {
                $this->line('  - '.$issue);
            }
        }

        if ($report['tables'] !== []) {
            $this->newLine();
            $this->info('Tables');
            $this->table(
                ['Table', 'Rows'],
                collect($report['tables'])
                    ->map(fn (array $table) => [$table['name'], $table['rows'] ?? '—'])
                    ->all(),
            );
        }

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
