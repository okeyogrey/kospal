<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\BackupService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\RestoreBackupRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RestoreController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant, BackupService $backups): Response
    {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/restore', [
            'supported' => $backups->isSupported(),
            'backups' => $backups->list(),
        ]);
    }

    public function restore(
        RestoreBackupRequest $request,
        TenantContext $tenant,
        BackupService $backups,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        if (! $backups->isSupported()) {
            return back()->with('error', 'Restore is not supported for this database.');
        }

        try {
            // Safety snapshot before replacing the live database.
            if ($backups->isSupported()) {
                $backups->create('pre-restore');
            }

            $backups->restore($request->validated('backup_id'));

            return redirect()
                ->route('restore.edit')
                ->with('success', 'Database restored. Sign in again if your session was interrupted.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
