<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\BackupService;
use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\CreateBackupRequest;
use App\Http\Requests\Desktop\UpdateBackupSettingsRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BackupController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(
        TenantContext $tenant,
        BackupService $backups,
        DesktopSettings $settings,
    ): Response {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/backup', [
            'supported' => $backups->isSupported(),
            'backups' => $backups->list(),
            'settings' => [
                'auto_backup' => (bool) $settings->get('auto_backup', false),
                'auto_backup_hour' => (int) $settings->get('auto_backup_hour', 2),
            ],
        ]);
    }

    public function updateSettings(
        UpdateBackupSettingsRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $settings->putMany($request->validated());

        return back()->with('success', 'Automatic backup settings saved.');
    }

    public function create(
        CreateBackupRequest $request,
        TenantContext $tenant,
        BackupService $backups,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        if (! $backups->isSupported()) {
            return back()->with('error', 'Backups are not supported for this database.');
        }

        try {
            $result = $backups->create($request->validated('label'));

            return back()->with('success', 'Backup created: '.$result['id']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
