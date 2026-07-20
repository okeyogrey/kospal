<?php

namespace App\Providers;

use App\Contracts\BackupService;
use App\Contracts\DatabaseRuntime;
use App\Contracts\DatabaseToolkit;
use App\Contracts\DesktopSettings;
use App\Contracts\DocumentPrinter;
use App\Contracts\FeatureFlagService;
use App\Contracts\LicensingService;
use App\Contracts\SynchronizationService;
use App\Contracts\UpdateService;
use App\Services\Deployment\Backup\SqliteFileBackupService;
use App\Services\Deployment\Backup\UnsupportedBackupService;
use App\Services\Deployment\Database\LaravelDatabaseRuntime;
use App\Services\Deployment\Database\LaravelDatabaseToolkit;
use App\Services\Deployment\Desktop\FileDesktopSettings;
use App\Services\Deployment\Licensing\LocalLicensingService;
use App\Services\Deployment\Licensing\SubscriptionLicensingService;
use App\Services\Deployment\Printing\BladeDomPdfDocumentPrinter;
use App\Services\Deployment\Synchronization\NoOpSynchronizationService;
use App\Services\Deployment\Updates\LocalUpdateService;
use App\Services\Deployment\Updates\UnsupportedUpdateService;
use App\Support\Deployment;
use App\Support\Plans\PlanLimitChecker;
use Illuminate\Support\ServiceProvider;

/**
 * Binds deployment-boundary services for desktop (default) or web mode.
 */
class DeploymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanLimitChecker::class);
        $this->app->singleton(FeatureFlagService::class, fn ($app) => $app->make(PlanLimitChecker::class));

        $this->app->bind(
            LicensingService::class,
            fn ($app) => Deployment::isDesktop()
                ? $app->make(LocalLicensingService::class)
                : $app->make(SubscriptionLicensingService::class),
        );

        $this->app->singleton(DocumentPrinter::class, BladeDomPdfDocumentPrinter::class);
        $this->app->singleton(DatabaseRuntime::class, LaravelDatabaseRuntime::class);
        $this->app->singleton(DatabaseToolkit::class, LaravelDatabaseToolkit::class);

        $this->app->bind(
            BackupService::class,
            fn ($app) => Deployment::isDesktop()
                ? $app->make(SqliteFileBackupService::class)
                : $app->make(UnsupportedBackupService::class),
        );

        $this->app->singleton(SynchronizationService::class, NoOpSynchronizationService::class);
        $this->app->singleton(DesktopSettings::class, FileDesktopSettings::class);

        $this->app->bind(
            UpdateService::class,
            fn ($app) => Deployment::isDesktop()
                ? $app->make(LocalUpdateService::class)
                : $app->make(UnsupportedUpdateService::class),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        // Apply SQLite pragmas for desktop installs when the DB already exists.
        if (Deployment::isDesktop() && config('database.default') === 'sqlite') {
            try {
                $this->app->make(DatabaseToolkit::class)->optimizeConnection();
            } catch (\Throwable) {
                // Database may not exist yet; db:prepare creates it.
            }
        }
    }
}
