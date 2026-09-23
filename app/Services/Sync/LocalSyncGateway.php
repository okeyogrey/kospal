<?php

namespace App\Services\Sync;

use App\Models\SyncDevice;

/**
 * In-process gateway used by tests and by a shop that is its own server.
 */
class LocalSyncGateway implements SyncGateway
{
    public function __construct(
        protected SyncHub $hub,
    ) {}

    public function register(string $serverUrl, array $payload): array
    {
        return $this->hub->register($payload);
    }

    public function join(string $serverUrl, array $payload): array
    {
        return $this->hub->join($payload);
    }

    public function push(string $serverUrl, string $token, array $operations): array
    {
        $device = $this->device($token);

        return $this->hub->push($device, $operations);
    }

    public function pull(string $serverUrl, string $token, int $after): array
    {
        return $this->hub->pull($this->device($token), $after);
    }

    public function regenerate(string $serverUrl, string $token): array
    {
        return $this->hub->regenerateJoinCode($this->device($token));
    }

    private function device(string $token): SyncDevice
    {
        $device = $this->hub->deviceFromToken($token);

        if ($device === null) {
            throw new \RuntimeException('This computer is no longer linked to the shop server.');
        }

        return $device;
    }
}
