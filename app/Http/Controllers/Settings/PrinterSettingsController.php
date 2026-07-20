<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\UpdatePrinterSettingsRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PrinterSettingsController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant, DesktopSettings $settings): Response
    {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/printer', [
            'settings' => [
                'printer_name' => $settings->get('printer_name') ? (string) $settings->get('printer_name') : '',
                'receipt_width' => (string) $settings->get('receipt_width', '80'),
            ],
        ]);
    }

    public function update(
        UpdatePrinterSettingsRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $validated = $request->validated();
        $settings->putMany([
            'printer_name' => filled($validated['printer_name'] ?? null)
                ? $validated['printer_name']
                : null,
            'receipt_width' => $validated['receipt_width'],
        ]);

        return back()->with('success', 'Printer settings saved.');
    }
}
