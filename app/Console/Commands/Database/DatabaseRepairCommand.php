<?php

namespace App\Console\Commands\Database;

use App\Contracts\DatabaseToolkit;
use Illuminate\Console\Command;

class DatabaseRepairCommand extends Command
{
    protected $signature = 'db:repair
                            {--no-vacuum : Skip SQLite VACUUM}
                            {--no-reindex : Skip SQLite REINDEX}
                            {--no-backup : Skip creating a backup before repair}';

    protected $description = 'Repair the database (backup, REINDEX/VACUUM on SQLite, apply pending migrations)';

    public function handle(DatabaseToolkit $toolkit): int
    {
        $this->warn('Starting database repair…');

        $result = $toolkit->repair([
            'vacuum' => ! $this->option('no-vacuum'),
            'reindex' => ! $this->option('no-reindex'),
            'backup_first' => ! $this->option('no-backup'),
        ]);

        foreach ($result['messages'] as $message) {
            $this->line('  '.$message);
        }

        $this->newLine();
        $this->info('Actions: '.implode(', ', $result['actions']));

        if (! $result['ok']) {
            $this->error('Repair finished with remaining issues. Run php artisan db:diagnose.');

            return self::FAILURE;
        }

        $this->info('Repair completed successfully.');

        return self::SUCCESS;
    }
}
