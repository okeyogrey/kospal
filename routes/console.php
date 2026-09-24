<?php

use App\Contracts\BackupService;
use App\Contracts\DesktopSettings;
use App\Models\SyncLink;
use App\Support\Deployment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shifts:auto-close')->everyFifteenMinutes();

Schedule::command('referrals:qualify')->daily();

Schedule::command('referrals:sync')
    ->hourly()
    ->when(fn () => Deployment::isDesktop() && filled(config('kospal.referral.server_url')));

Schedule::command('sync:run')
    ->everyMinute()
    ->when(fn () => Schema::hasTable('sync_links') && SyncLink::query()->exists());

Schedule::command('updates:pull')
    ->hourly()
    ->when(fn () => Deployment::isDesktop());

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
