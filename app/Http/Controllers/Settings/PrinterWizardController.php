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

class PrinterWizardController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant, DesktopSettings $settings): Response
    {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/printer-wizard', [
            'settings' => [
                'printer_name' => $settings->get('printer_name') ? (string) $settings->get('printer_name') : '',
                'receipt_width' => (string) $settings->get('receipt_width', '80'),
            ],
            'test_receipt_url' => route('sales.index'),
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
            'printer_wizard_completed' => true,
        ]);

        return redirect()
            ->route('printer.wizard')
            ->with('success', 'Printer setup complete.');
    }
}
