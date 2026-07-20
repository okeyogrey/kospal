<?php

namespace App\Services\Deployment\Desktop;

use App\Contracts\DesktopSettings;
use Illuminate\Support\Facades\File;

/**
 * Persists desktop preferences to a JSON file, layered over config defaults.
 */
class FileDesktopSettings implements DesktopSettings
{
    /** @var array<string, mixed>|null */
    protected ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $stored = $this->readStored();
        $stored[$key] = $value;
        $this->writeStored($stored);
        $this->cache = null;
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        /** @var array<string, mixed> $configured */
        $configured = config('deployment.settings', []);

        return $this->cache = array_merge($configured, $this->readStored());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values): void
    {
        $stored = $this->readStored();

        foreach ($values as $key => $value) {
            $stored[$key] = $value;
        }

        $this->writeStored($stored);
        $this->cache = null;
    }

    public function path(): string
    {
        $configured = config('deployment.settings_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/desktop-settings.json');
    }

    /**
     * @return array<string, mixed>
     */
    protected function readStored(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function writeStored(array $values): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
