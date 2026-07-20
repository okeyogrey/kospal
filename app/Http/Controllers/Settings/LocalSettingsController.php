<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\UpdateLocalSettingsRequest;
use App\Support\Deployment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocalSettingsController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant, DesktopSettings $settings): Response
    {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/local', [
            'settings' => [
                'update_channel' => (string) $settings->get('update_channel', 'stable'),
                'auto_backup' => (bool) $settings->get('auto_backup', false),
                'auto_backup_hour' => (int) $settings->get('auto_backup_hour', 2),
                'printer_name' => $settings->get('printer_name'),
                'receipt_width' => (string) $settings->get('receipt_width', '80'),
                'data_directory' => $settings->get('data_directory'),
            ],
            'deployment' => [
                'mode' => Deployment::mode(),
                'version' => (string) config('deployment.version'),
            ],
        ]);
    }

    public function update(
        UpdateLocalSettingsRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $settings->putMany($request->validated());

        return back()->with('success', 'Local settings saved.');
    }
}
