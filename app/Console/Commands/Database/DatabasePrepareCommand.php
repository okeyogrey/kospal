<?php

namespace App\Console\Commands\Database;

use App\Contracts\DatabaseToolkit;
use Illuminate\Console\Command;

class DatabasePrepareCommand extends Command
{
    protected $signature = 'db:prepare
                            {--seed : Run database seeders after migrating}
                            {--force : Required in production}';

    protected $description = 'Ensure SQLite file/pragmas, run pending migrations, and record schema version';

    public function handle(DatabaseToolkit $toolkit): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Use --force to prepare the database in production.');

            return self::FAILURE;
        }

        $this->info('Preparing database…');
        $toolkit->ensureReady();

        $result = $toolkit->migrate((bool) $this->option('seed'));

        if ($result['ran'] === []) {
            $this->info('Nothing to migrate. Schema version '.$result['schema_version'].'.');
        } else {
            $this->info('Migrated:');
            foreach ($result['ran'] as $migration) {
                $this->line('  - '.$migration);
            }
            $this->info('Schema version '.$result['schema_version'].'.');
        }

        return self::SUCCESS;
    }
}
