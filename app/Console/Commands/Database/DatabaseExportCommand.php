<?php

namespace App\Console\Commands\Database;

use App\Contracts\DatabaseToolkit;
use Illuminate\Console\Command;

class DatabaseExportCommand extends Command
{
    protected $signature = 'db:export
                            {--path= : Destination .sql file path}';

    protected $description = 'Export portable SQL for migrating from SQLite to MySQL/PostgreSQL';

    public function handle(DatabaseToolkit $toolkit): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : null;

        $this->info('Exporting database…');
        $result = $toolkit->exportSql($path);

        $this->info('Wrote '.$result['tables'].' table(s) to:');
        $this->line('  '.$result['path']);
        $this->line('  '.number_format($result['bytes']).' bytes');
        $this->comment('Review column types before importing into MySQL or PostgreSQL.');

        return self::SUCCESS;
    }
}
