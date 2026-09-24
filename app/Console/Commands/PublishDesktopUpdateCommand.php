<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishDesktopUpdateCommand extends Command
{
    protected $signature = 'desktop-update:publish {zip : Path to the desktop app zip} {--release=} {--notes=}';

    protected $description = 'Publish a desktop app package that installed shop computers can download';

    public function handle(): int
    {
        $source = (string) $this->argument('zip');

        if (! is_file($source)) {
            $this->error('That zip file was not found.');

            return self::FAILURE;
        }

        $version = $this->option('release');

        if (! is_string($version) || $version === '') {
            $this->error('Pass the version, for example --release=0.1.1');

            return self::FAILURE;
        }

        $package = (string) config('deployment.updates.package_path');
        $manifest = (string) config('deployment.updates.manifest_path');
        File::ensureDirectoryExists(dirname($package));
        File::copy($source, $package);

        $sha256 = hash_file('sha256', $package);
        $notes = $this->option('notes');

        File::put($manifest, json_encode([
            'version' => $version,
            'notes' => is_string($notes) ? $notes : null,
            'sha256' => $sha256,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Published desktop {$version}.");

        return self::SUCCESS;
    }
}
