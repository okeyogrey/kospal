<?php

use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\BackupWizardController;
use App\Http\Controllers\Settings\DatabaseSettingsController;
use App\Http\Controllers\Settings\ErrorReportingController;
use App\Http\Controllers\Settings\HealthController;
use App\Http\Controllers\Settings\LicenseController;
use App\Http\Controllers\Settings\LocalSettingsController;
use App\Http\Controllers\Settings\PrinterSettingsController;
use App\Http\Controllers\Settings\PrinterWizardController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RestoreController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\StorageSettingsController;
use App\Http\Controllers\Settings\UpdateManagerController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::put('settings/approval-pin', [SecurityController::class, 'updateApprovalPin'])
        ->middleware(['business', 'throttle:6,1'])
        ->name('approval-pin.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::middleware(['auth', 'verified', 'business'])->group(function () {
    Route::get('settings/license', [LicenseController::class, 'edit'])->name('license.edit');
    Route::post('settings/license/activate-online', [LicenseController::class, 'activateOnline'])
        ->middleware('throttle:sensitive')
        ->name('license.activate-online');
    Route::post('settings/license/activate-offline', [LicenseController::class, 'activateOffline'])
        ->middleware('throttle:sensitive')
        ->name('license.activate-offline');

    // Desktop installation settings (owners only; controllers abort on web mode).
    Route::get('settings/local', [LocalSettingsController::class, 'edit'])->name('local.edit');
    Route::put('settings/local', [LocalSettingsController::class, 'update'])->name('local.update');

    Route::get('settings/backup', [BackupController::class, 'edit'])->name('backup.edit');
    Route::get('settings/backup/wizard', [BackupWizardController::class, 'edit'])->name('backup.wizard');
    Route::put('settings/backup/wizard', [BackupWizardController::class, 'updateSettings'])->name('backup.wizard.update');
    Route::post('settings/backup/wizard', [BackupWizardController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('backup.wizard.create');
    Route::put('settings/backup', [BackupController::class, 'updateSettings'])->name('backup.update');
    Route::post('settings/backup', [BackupController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('backup.create');

    Route::get('settings/restore', [RestoreController::class, 'edit'])->name('restore.edit');
    Route::post('settings/restore', [RestoreController::class, 'restore'])
        ->middleware('throttle:sensitive')
        ->name('restore.store');

    Route::get('settings/health', [HealthController::class, 'edit'])->name('health.edit');

    Route::get('settings/error-reporting', [ErrorReportingController::class, 'edit'])->name('error-reporting.edit');
    Route::put('settings/error-reporting', [ErrorReportingController::class, 'update'])->name('error-reporting.update');
    Route::get('settings/error-reporting/download', [ErrorReportingController::class, 'download'])
        ->middleware('throttle:10,1')
        ->name('error-reporting.download');

    Route::get('settings/updates', [UpdateManagerController::class, 'edit'])->name('updates.edit');
    Route::post('settings/updates/check', [UpdateManagerController::class, 'check'])
        ->middleware('throttle:10,1')
        ->name('updates.check');
    Route::put('settings/updates/channel', [UpdateManagerController::class, 'updateChannel'])
        ->name('updates.channel');

    Route::get('settings/printer', [PrinterSettingsController::class, 'edit'])->name('printer.edit');
    Route::get('settings/printer/wizard', [PrinterWizardController::class, 'edit'])->name('printer.wizard');
    Route::put('settings/printer/wizard', [PrinterWizardController::class, 'update'])->name('printer.wizard.update');
    Route::put('settings/printer', [PrinterSettingsController::class, 'update'])->name('printer.update');

    Route::get('settings/database', [DatabaseSettingsController::class, 'edit'])->name('database.edit');
    Route::post('settings/database/repair', [DatabaseSettingsController::class, 'repair'])
        ->middleware('throttle:sensitive')
        ->name('database.repair');
    Route::post('settings/database/migrate', [DatabaseSettingsController::class, 'migrate'])
        ->middleware('throttle:sensitive')
        ->name('database.migrate');
    Route::post('settings/database/export', [DatabaseSettingsController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('database.export');

    Route::get('settings/data-storage', [StorageSettingsController::class, 'edit'])->name('data-storage.edit');
    Route::put('settings/data-storage', [StorageSettingsController::class, 'update'])->name('data-storage.update');
});
