<?php

namespace App\Support\Licensing;

use Illuminate\Support\Facades\File;

/**
 * Stable installation fingerprint used for license binding.
 *
 * Stored outside the tenant database so the same machine ID survives
 * business re-onboarding on this install.
 */
final class MachineId
{
    public function get(): string
    {
        $path = $this->path();

        if (File::exists($path)) {
            $existing = trim((string) File::get($path));

            if ($existing !== '' && $this->isValid($existing)) {
                return $existing;
            }
        }

        $id = $this->generate();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $id.PHP_EOL);

        return $id;
    }

    public function path(): string
    {
        $configured = config('deployment.license.machine_id_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/license/machine_id');
    }

    public function isValid(string $machineId): bool
    {
        return (bool) preg_match('/^KOSPAL-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $machineId);
    }

    protected function generate(): string
    {
        $raw = strtoupper(bin2hex(random_bytes(8)));

        return sprintf(
            'KOSPAL-%s-%s-%s-%s',
            substr($raw, 0, 4),
            substr($raw, 4, 4),
            substr($raw, 8, 4),
            substr($raw, 12, 4),
        );
    }
}
