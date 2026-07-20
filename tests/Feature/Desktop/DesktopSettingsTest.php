<?php

use App\Contracts\BackupService;
use App\Contracts\DesktopSettings;
use App\Contracts\UpdateService;
use App\Enums\BusinessRole;
use App\Services\Deployment\Desktop\FileDesktopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    $this->settingsPath = storage_path('framework/testing/desktop-settings-'.uniqid('', true).'.json');
    config(['deployment.settings_path' => $this->settingsPath]);

    // Re-bind so the singleton picks up the test path.
    app()->forgetInstance(DesktopSettings::class);
    app()->singleton(DesktopSettings::class, FileDesktopSettings::class);
});

afterEach(function () {
    if (isset($this->settingsPath) && File::exists($this->settingsPath)) {
        File::delete($this->settingsPath);
    }
});

it('renders desktop settings pages for owners', function (string $route) {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->get(route($route))
        ->assertOk();
})->with([
    'local.edit',
    'backup.edit',
    'restore.edit',
    'health.edit',
    'updates.edit',
    'printer.edit',
    'database.edit',
    'data-storage.edit',
]);

it('hides desktop settings from non-owners', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $manager = $this->addMember($business, BusinessRole::Manager, null, [$branch->id]);

    $this->actingAs($manager)
        ->get(route('backup.edit'))
        ->assertForbidden();
});

it('returns 404 for desktop settings in web mode', function () {
    config(['deployment.mode' => 'web']);
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->get(route('health.edit'))
        ->assertNotFound();
});

it('persists printer and storage settings', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->put(route('printer.update'), [
            'printer_name' => 'EPSON TM-T20',
            'receipt_width' => '58',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(DesktopSettings::class);

    expect($settings->get('printer_name'))->toBe('EPSON TM-T20')
        ->and($settings->get('receipt_width'))->toBe('58');

    $directory = storage_path('framework/testing/kospal-data-'.uniqid('', true));

    $this->actingAs($owner)
        ->put(route('data-storage.update'), [
            'data_directory' => $directory,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($settings->get('data_directory'))->toBe($directory)
        ->and(File::isDirectory($directory.DIRECTORY_SEPARATOR.'backups'))->toBeTrue();

    File::deleteDirectory($directory);
});

it('saves automatic backup schedule', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->put(route('backup.update'), [
            'auto_backup' => true,
            'auto_backup_hour' => 3,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(DesktopSettings::class);

    expect($settings->get('auto_backup'))->toBeTrue()
        ->and($settings->get('auto_backup_hour'))->toBe(3);
});

it('checks updates on the desktop update manager', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    expect(app(UpdateService::class)->isSupported())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('updates.check'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/updates')
            ->where('supported', true)
            ->has('check')
        );
});

it('exposes the backup run artisan command', function () {
    // Without --force and with auto_backup off, the command exits cleanly.
    $this->artisan('backup:run')->assertSuccessful();
});

it('lists backups through the backup service contract', function () {
    expect(app(BackupService::class)->list())->toBeArray();
});
