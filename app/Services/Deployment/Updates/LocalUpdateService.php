<?php

namespace App\Services\Deployment\Updates;

use App\Contracts\DesktopSettings;
use App\Contracts\UpdateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;
use ZipArchive;

/**
 * Desktop update channel checks against an optional feed URL.
 *
 * When no feed is configured, reports the current version with no update available.
 */
class LocalUpdateService implements UpdateService
{
    public function __construct(
        protected DesktopSettings $settings,
    ) {}

    public function isSupported(): bool
    {
        return true;
    }

    public function currentVersion(): string
    {
        $releaseFile = base_path('.desktop-release');

        if (is_file($releaseFile)) {
            $release = trim((string) file_get_contents($releaseFile));

            if ($release !== '') {
                return $release;
            }
        }

        return (string) config('deployment.version', config('app.version', '0.0.0'));
    }

    public function check(): ?array
    {
        $channel = (string) $this->settings->get('update_channel', 'stable');
        $feedUrl = config('deployment.updates.feed_url');

        if (! is_string($feedUrl) || $feedUrl === '') {
            return [
                'available' => false,
                'version' => null,
                'notes' => "No update feed configured. Channel: {$channel}.",
            ];
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get($feedUrl, ['channel' => $channel]);

            if (! $response->successful()) {
                return [
                    'available' => false,
                    'version' => null,
                    'notes' => 'Update feed could not be reached (HTTP '.$response->status().').',
                ];
            }

            /** @var array{version?: mixed, notes?: mixed, channels?: mixed} $payload */
            $payload = $response->json() ?? [];

            $remoteVersion = $this->resolveRemoteVersion($payload, $channel);
            $notes = isset($payload['notes']) && is_string($payload['notes'])
                ? $payload['notes']
                : null;

            if ($remoteVersion === null) {
                return [
                    'available' => false,
                    'version' => null,
                    'notes' => 'Update feed did not include a version for this channel.',
                ];
            }

            $available = version_compare($remoteVersion, $this->currentVersion(), '>');
            $url = isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null;
            $sha256 = isset($payload['sha256']) && is_string($payload['sha256']) ? $payload['sha256'] : null;

            return [
                'available' => $available,
                'version' => $remoteVersion,
                'notes' => $available
                    ? ($notes ?? "Version {$remoteVersion} is available on the {$channel} channel.")
                    : "You are on the latest {$channel} release.",
                'url' => $url,
                'sha256' => $sha256,
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'version' => null,
                'notes' => 'Update check failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRemoteVersion(array $payload, string $channel): ?string
    {
        if (isset($payload['channels']) && is_array($payload['channels'])) {
            $channelPayload = $payload['channels'][$channel] ?? null;

            if (is_array($channelPayload) && isset($channelPayload['version']) && is_scalar($channelPayload['version'])) {
                return (string) $channelPayload['version'];
            }

            if (is_string($channelPayload) || is_numeric($channelPayload)) {
                return (string) $channelPayload;
            }
        }

        if (isset($payload['version']) && is_scalar($payload['version'])) {
            return (string) $payload['version'];
        }

        return null;
    }

    public function pull(): array
    {
        $check = $this->check();

        if ($check === null || ! ($check['available'] ?? false)) {
            return [
                'downloaded' => false,
                'version' => is_array($check) ? ($check['version'] ?? null) : null,
                'notes' => is_array($check) ? ($check['notes'] ?? 'No update is available.') : 'No update is available.',
            ];
        }

        $url = $check['url'] ?? null;
        $sha256 = $check['sha256'] ?? null;
        $version = $check['version'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($sha256) || $sha256 === '' || ! is_string($version)) {
            return [
                'downloaded' => false,
                'version' => is_string($version) ? $version : null,
                'notes' => 'The update feed did not include a download.',
            ];
        }

        $directory = $this->pendingDirectory();
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        $zipPath = $directory.'.zip';
        File::ensureDirectoryExists(dirname($zipPath));
        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            return [
                'downloaded' => false,
                'version' => $version,
                'notes' => 'The update could not be downloaded.',
            ];
        }

        File::put($zipPath, $response->body());

        $actual = hash_file('sha256', $zipPath);

        if (! is_string($actual) || ! hash_equals(strtolower($sha256), strtolower($actual))) {
            File::delete($zipPath);

            return [
                'downloaded' => false,
                'version' => $version,
                'notes' => 'The downloaded update did not match its checksum.',
            ];
        }

        if (! $this->extract($zipPath, $directory)) {
            File::delete($zipPath);
            File::deleteDirectory($directory);

            return [
                'downloaded' => false,
                'version' => $version,
                'notes' => 'The update package could not be opened.',
            ];
        }

        File::delete($zipPath);

        if (! is_file($directory.DIRECTORY_SEPARATOR.'artisan')) {
            File::deleteDirectory($directory);

            return [
                'downloaded' => false,
                'version' => $version,
                'notes' => 'The update package is missing the application.',
            ];
        }

        return [
            'downloaded' => true,
            'version' => $version,
            'notes' => "Version {$version} is ready. Close KOSPAL and open it again to finish installing.",
        ];
    }

    public function pendingDirectory(): string
    {
        $configured = config('deployment.updates.pending_directory');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return dirname(base_path()).DIRECTORY_SEPARATOR.'pending-update-app';
    }

    private function extract(string $zipPath, string $directory): bool
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $extracted = $zip->extractTo($directory);
                $zip->close();

                return $extracted;
            }
        }

        $result = Process::run([
            'powershell',
            '-NoProfile',
            '-Command',
            'Expand-Archive -LiteralPath '.escapeshellarg($zipPath).' -DestinationPath '.escapeshellarg($directory).' -Force',
        ]);

        return $result->successful();
    }
}
