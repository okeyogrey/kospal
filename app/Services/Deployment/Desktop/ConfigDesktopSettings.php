<?php

namespace App\Services\Deployment\Desktop;

use App\Contracts\DesktopSettings;

/**
 * Config-backed desktop settings store (in-memory overrides for tests).
 *
 * Production desktop installs use FileDesktopSettings instead.
 */
class ConfigDesktopSettings implements DesktopSettings
{
    /** @var array<string, mixed> */
    protected array $overrides = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        return config('deployment.settings.'.$key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->overrides[$key] = $value;
    }

    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->overrides[$key] = $value;
        }
    }

    public function all(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = config('deployment.settings', []);

        return array_merge($configured, $this->overrides);
    }
}
