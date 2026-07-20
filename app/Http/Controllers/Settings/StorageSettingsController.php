<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\UpdateStorageSettingsRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class StorageSettingsController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant, DesktopSettings $settings): Response
    {
        $this->desktopBusiness($tenant);

        $dataDirectory = $settings->get('data_directory');
        $dataDirectory = is_string($dataDirectory) && $dataDirectory !== '' ? $dataDirectory : null;

        $backupDirectory = $dataDirectory
            ? rtrim($dataDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'backups'
            : storage_path('app/backups');

        $exportDirectory = $dataDirectory
            ? rtrim($dataDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'exports'
            : storage_path('app/exports');

        return Inertia::render('settings/data-storage', [
            'settings' => [
                'data_directory' => $dataDirectory ?? '',
            ],
            'paths' => [
                'data_directory' => $dataDirectory,
                'backup_directory' => $backupDirectory,
                'export_directory' => $exportDirectory,
                'settings_file' => method_exists($settings, 'path') ? $settings->path() : storage_path('app/desktop-settings.json'),
            ],
            'disk' => $this->diskStats($dataDirectory ?? storage_path('app')),
        ]);
    }

    public function update(
        UpdateStorageSettingsRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $directory = $request->validated('data_directory');
        $directory = is_string($directory) ? trim($directory) : '';

        if ($directory !== '') {
            File::ensureDirectoryExists($directory);
            File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.'backups');
            File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.'exports');
        }

        $settings->set('data_directory', $directory !== '' ? $directory : null);

        return back()->with('success', 'Storage settings saved.');
    }

    /**
     * @return array{free_bytes: int|null, total_bytes: int|null, path: string}
     */
    protected function diskStats(string $path): array
    {
        $probe = File::isDirectory($path) ? $path : dirname($path);

        $free = @disk_free_space($probe);
        $total = @disk_total_space($probe);

        return [
            'path' => $probe,
            'free_bytes' => is_float($free) || is_int($free) ? (int) $free : null,
            'total_bytes' => is_float($total) || is_int($total) ? (int) $total : null,
        ];
    }
}
