<?php

namespace App\Services\Deployment\Backup;

use App\Contracts\BackupService;
use App\Contracts\DatabaseRuntime;
use App\Contracts\DesktopSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * File-copy backups for SQLite desktop installs.
 */
class SqliteFileBackupService implements BackupService
{
    public function __construct(
        protected DatabaseRuntime $database,
        protected DesktopSettings $settings,
    ) {}

    public function isSupported(): bool
    {
        return $this->database->isSqlite() && $this->database->databasePath() !== null;
    }

    public function list(): array
    {
        if (! $this->isSupported()) {
            return [];
        }

        $dir = $this->backupDirectory();

        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sqlite'))
            ->map(fn ($file) => [
                'id' => $file->getFilename(),
                'label' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                'created_at' => date('c', $file->getMTime()),
                'size_bytes' => $file->getSize(),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function create(?string $label = null): array
    {
        if (! $this->isSupported()) {
            throw new RuntimeException('SQLite file backups require a file-backed SQLite database.');
        }

        $source = $this->database->databasePath();
        abort_unless(is_string($source) && File::exists($source), 500, 'Database file not found.');

        $dir = $this->backupDirectory();
        File::ensureDirectoryExists($dir);

        $slug = Str::slug($label ?: 'backup') ?: 'backup';
        $id = sprintf('%s-%s.sqlite', now()->format('Ymd-His'), $slug);
        $path = $dir.DIRECTORY_SEPARATOR.$id;

        File::copy($source, $path);

        return [
            'id' => $id,
            'path' => $path,
        ];
    }

    public function restore(string $backupId): void
    {
        if (! $this->isSupported()) {
            throw new RuntimeException('SQLite file restore requires a file-backed SQLite database.');
        }

        if (! preg_match('/^[A-Za-z0-9._-]+\.sqlite$/', $backupId)) {
            throw new RuntimeException('Invalid backup id.');
        }

        $source = $this->backupDirectory().DIRECTORY_SEPARATOR.$backupId;
        $target = $this->database->databasePath();

        if (! is_string($target) || ! File::exists($source)) {
            throw new RuntimeException('Backup not found.');
        }

        File::copy($source, $target);
    }

    protected function backupDirectory(): string
    {
        $configured = $this->settings->get('data_directory');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'backups';
        }

        return storage_path('app/backups');
    }
}
