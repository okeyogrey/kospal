<?php

namespace App\Enums;

enum Plan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array{name: string, description?: string, max_branches: int, max_staff: int|null, features: list<string>}
     */
    public function config(): array
    {
        /** @var array{name: string, description?: string, max_branches: int, max_staff: int|null, features: list<string>} $config */
        $config = config('kospal.plans.'.$this->value);

        return $config;
    }

    public function maxBranches(): int
    {
        return (int) $this->config()['max_branches'];
    }

    public function maxStaff(): ?int
    {
        $max = $this->config()['max_staff'];

        return $max === null ? null : (int) $max;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->config()['features'], true);
    }

    public function allowsAuditLogs(): bool
    {
        return $this->hasFeature('audit_logs');
    }

    public function allowsAdvancedReports(): bool
    {
        return $this->hasFeature('advanced_reports');
    }

    public function allowsCsvExport(): bool
    {
        return $this->hasFeature('csv_export');
    }

    public function allowsConsolidatedReports(): bool
    {
        return $this->hasFeature('consolidated_reports');
    }

    public function allowsEnhancedExports(): bool
    {
        return $this === self::Enterprise;
    }
}
