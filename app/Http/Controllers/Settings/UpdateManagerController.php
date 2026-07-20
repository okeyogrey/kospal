<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DesktopSettings;
use App\Contracts\UpdateService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\UpdateLocalSettingsRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpdateManagerController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(
        TenantContext $tenant,
        UpdateService $updates,
        DesktopSettings $settings,
    ): Response {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/updates', [
            'supported' => $updates->isSupported(),
            'current_version' => $updates->currentVersion(),
            'channel' => (string) $settings->get('update_channel', 'stable'),
            'feed_configured' => filled(config('deployment.updates.feed_url')),
            'check' => null,
        ]);
    }

    public function check(
        TenantContext $tenant,
        UpdateService $updates,
        DesktopSettings $settings,
    ): Response|RedirectResponse {
        $this->desktopBusiness($tenant);

        if (! $updates->isSupported()) {
            return back()->with('error', 'Updates are not supported in this deployment.');
        }

        return Inertia::render('settings/updates', [
            'supported' => true,
            'current_version' => $updates->currentVersion(),
            'channel' => (string) $settings->get('update_channel', 'stable'),
            'feed_configured' => filled(config('deployment.updates.feed_url')),
            'check' => $updates->check(),
        ]);
    }

    public function updateChannel(
        UpdateLocalSettingsRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $settings->putMany($request->validated());

        return back()->with('success', 'Update channel saved.');
    }
}
