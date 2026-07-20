<?php

namespace App\Console\Commands\Desktop;

use App\Contracts\BackupService;
use App\Contracts\DesktopSettings;
use App\Support\Deployment;
use Illuminate\Console\Command;
use Throwable;

class RunAutomaticBackupCommand extends Command
{
    protected $signature = 'backup:run
                            {--label= : Optional backup label}
                            {--force : Run even when automatic backup is disabled}';

    protected $description = 'Create a desktop SQLite backup (honours auto_backup unless --force)';

    public function handle(BackupService $backups, DesktopSettings $settings): int
    {
        if (! Deployment::isDesktop()) {
            $this->warn('Backups are only available in desktop mode.');

            return self::SUCCESS;
        }

        if (! $backups->isSupported()) {
            $this->warn('File backups are not supported for this database connection.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $auto = (bool) $settings->get('auto_backup', false);

        if (! $force && ! $auto) {
            $this->info('Automatic backup is disabled.');

            return self::SUCCESS;
        }

        try {
            $label = $this->option('label');
            $label = is_string($label) && $label !== '' ? $label : ($force ? 'manual' : 'automatic');
            $result = $backups->create($label);
            $this->info('Backup created: '.$result['id']);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
