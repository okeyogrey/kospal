<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\BackupService;
use App\Contracts\DatabaseToolkit;
use App\Contracts\DesktopSettings;
use App\Contracts\UpdateService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(
        TenantContext $tenant,
        DatabaseToolkit $toolkit,
        BackupService $backups,
        UpdateService $updates,
        DesktopSettings $settings,
    ): Response {
        $this->desktopBusiness($tenant);

        $diagnosis = $toolkit->diagnose();
        $backupList = $backups->list();

        return Inertia::render('settings/health', [
            'diagnosis' => $diagnosis,
            'backup' => [
                'supported' => $backups->isSupported(),
                'count' => count($backupList),
                'latest' => $backupList[0] ?? null,
                'auto_backup' => (bool) $settings->get('auto_backup', false),
            ],
            'updates' => [
                'supported' => $updates->isSupported(),
                'current_version' => $updates->currentVersion(),
                'channel' => (string) $settings->get('update_channel', 'stable'),
            ],
            'storage' => [
                'data_directory' => $settings->get('data_directory'),
                'settings_path' => method_exists($settings, 'path') ? $settings->path() : null,
            ],
        ]);
    }
}
