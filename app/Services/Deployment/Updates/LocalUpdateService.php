<?php

namespace App\Services\Deployment\Updates;

use App\Contracts\DesktopSettings;
use App\Contracts\UpdateService;
use Illuminate\Support\Facades\Http;
use Throwable;

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

            return [
                'available' => $available,
                'version' => $remoteVersion,
                'notes' => $available
                    ? ($notes ?? "Version {$remoteVersion} is available on the {$channel} channel.")
                    : "You are on the latest {$channel} release.",
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
}
