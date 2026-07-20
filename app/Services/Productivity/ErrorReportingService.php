<?php

namespace App\Services\Productivity;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ErrorReportingService
{
    /**
     * @return list<array{level: string, message: string, context: string|null, logged_at: string|null}>
     */
    public function recentErrors(int $limit = 25): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return [];
        }

        $lines = $this->tailLines($path, 400);
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'logged_at' => $matches[1],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'context' => null,
                ];

                continue;
            }

            if ($current !== null) {
                $current['context'] = trim(($current['context'] ?? '')."\n".$line);
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return collect($entries)
            ->filter(fn (array $entry): bool => in_array($entry['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true))
            ->take(-$limit)
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * @return array{generated_at: string, app_version: string, deployment_mode: string, php_version: string, laravel_version: string, database: array<string, mixed>, recent_errors: list<array<string, mixed>>}
     */
    public function diagnosticBundle(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app_version' => (string) config('deployment.version'),
            'deployment_mode' => (string) config('deployment.mode'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'connection' => (string) config('database.default'),
                'driver' => (string) config('database.connections.'.config('database.default').'.driver'),
            ],
            'recent_errors' => $this->recentErrors(50),
        ];
    }

    /**
     * @return list<string>
     */
    private function tailLines(string $path, int $maxLines): array
    {
        $content = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];

        return array_slice($lines, -$maxLines);
    }

    public function redact(string $message): string
    {
        return Str::of($message)
            ->replaceMatches('/(password|token|secret|key)(["\']?\s*[:=]\s*["\']?)[^\s,"\']+/i', '$1$2[redacted]')
            ->toString();
    }
}
