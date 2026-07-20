<?php

namespace App\Contracts;

/**
 * Deployment-local preferences (printer, data paths, update channel, etc.).
 *
 * Distinct from business policy settings (e.g. cashiers_can_log_expenses)
 * and from SaaS platform settings (payment instructions).
 */
interface DesktopSettings
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * Persist multiple keys in one write.
     *
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values): void;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;
}
