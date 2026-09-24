<?php

use App\Services\Deployment\Updates\LocalUpdateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('publishes a desktop package and lets a shop computer download it', function () {
    $source = storage_path('framework/testing/source-update.zip');
    $package = storage_path('framework/testing/published-update.zip');
    $manifest = storage_path('framework/testing/published-update.json');
    $pending = storage_path('framework/testing/pending-update-app');

    File::ensureDirectoryExists(dirname($source));
    File::delete($source);
    File::delete($package);
    File::delete($manifest);
    File::deleteDirectory($pending);

    $zip = new ZipArchive;
    expect($zip->open($source, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('artisan', "<?php\n");
    $zip->close();

    config([
        'deployment.version' => '0.1.0',
        'deployment.updates.package_path' => $package,
        'deployment.updates.manifest_path' => $manifest,
        'deployment.updates.pending_directory' => $pending,
        'deployment.updates.feed_url' => 'http://updates.test/api/updates/desktop',
    ]);

    $this->artisan('desktop-update:publish', [
        'zip' => $source,
        '--release' => '0.1.1',
        '--notes' => 'Fixes the till',
    ])->assertSuccessful();

    expect(config('deployment.updates.manifest_path'))->toBe($manifest)
        ->and(is_file($manifest))->toBeTrue();

    $this->get('/api/updates/desktop')
        ->assertOk()
        ->assertJsonPath('version', '0.1.1')
        ->assertJsonPath('notes', 'Fixes the till');

    $hash = hash_file('sha256', $package);
    $bytes = File::get($package);

    Http::fake(function ($request) use ($hash, $bytes) {
        if (str_contains($request->url(), 'package')) {
            return Http::response($bytes, 200);
        }

        return Http::response([
            'version' => '0.1.1',
            'notes' => 'Fixes the till',
            'sha256' => $hash,
            'url' => 'http://updates.test/api/updates/desktop/package',
        ]);
    });

    $result = app(LocalUpdateService::class)->pull();

    expect($result['downloaded'])->toBeTrue()
        ->and(is_file($pending.DIRECTORY_SEPARATOR.'artisan'))->toBeTrue();

    File::delete([$source, $package, $manifest, $pending.'.zip']);
    File::deleteDirectory($pending);
});
