<?php

use App\Contracts\BackupService;
use App\Contracts\DesktopSettings;
use App\Support\Deployment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shifts:auto-close')->everyFifteenMinutes();

Schedule::command('backup:run')
    ->hourly()
    ->when(function () {
        if (! Deployment::isDesktop()) {
            return false;
        }

        if (! app(BackupService::class)->isSupported()) {
            return false;
        }

        $settings = app(DesktopSettings::class);

        if (! (bool) $settings->get('auto_backup', false)) {
            return false;
        }

        $hour = (int) $settings->get('auto_backup_hour', 2);

        return now()->hour === $hour;
    });
