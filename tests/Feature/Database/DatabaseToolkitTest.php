<?php

use App\Contracts\DatabaseToolkit;
use App\Support\Database\SchemaVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('tracks schema version after migrations', function () {
    expect(Schema::hasTable('database_versions'))->toBeTrue()
        ->and(SchemaVersion::current())->toBe(SchemaVersion::expected())
        ->and(SchemaVersion::expected())->toBe((int) config('deployment.database.schema_version'));
});

it('diagnoses a healthy sqlite database', function () {
    $report = app(DatabaseToolkit::class)->diagnose();

    expect($report['ok'])->toBeTrue()
        ->and($report['driver'])->toBe('sqlite')
        ->and($report['integrity']['ok'])->toBeTrue()
        ->and($report['foreign_keys']['ok'])->toBeTrue()
        ->and($report['migrations']['pending'])->toBe([])
        ->and($report['schema_version'])->toBe(SchemaVersion::expected());
});

it('repairs sqlite without failing on memory databases', function () {
    $result = app(DatabaseToolkit::class)->repair([
        'vacuum' => true,
        'reindex' => true,
        'backup_first' => true,
    ]);

    expect($result['ok'])->toBeTrue()
        ->and($result['actions'])->toContain('optimize');
});

it('exports portable sql', function () {
    $path = storage_path('framework/testing/kospal-export-'.uniqid('', true).'.sql');

    $result = app(DatabaseToolkit::class)->exportSql($path);

    expect($result['path'])->toBe($path)
        ->and($result['tables'])->toBeGreaterThan(0)
        ->and(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('KOSPAL portable SQL export');

    File::delete($path);
});

it('exposes database artisan commands', function () {
    $this->artisan('db:diagnose')->assertSuccessful();
    $this->artisan('db:version')->assertSuccessful();
    $this->artisan('db:prepare')->assertSuccessful();
});

it('adds optimized query indexes', function () {
    $indexes = collect(Schema::getIndexes('businesses'))->pluck('name');

    expect($indexes)->toContain('businesses_subscription_expiry_index')
        ->and($indexes)->toContain('businesses_active_plan_index');
});
