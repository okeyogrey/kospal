<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Productivity\UpdateErrorReportingRequest;
use App\Services\Productivity\ErrorReportingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ErrorReportingController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(
        TenantContext $tenant,
        DesktopSettings $settings,
        ErrorReportingService $errors,
    ): Response {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/error-reporting', [
            'settings' => [
                'error_reporting_enabled' => (bool) $settings->get('error_reporting_enabled', true),
                'include_diagnostics' => (bool) $settings->get('include_diagnostics', true),
            ],
            'recent_errors' => collect($errors->recentErrors())
                ->map(fn (array $entry) => [
                    ...$entry,
                    'message' => $errors->redact($entry['message']),
                    'context' => $entry['context'] !== null
                        ? $errors->redact($entry['context'])
                        : null,
                ])
                ->all(),
        ]);
    }

    public function update(
        UpdateErrorReportingRequest $request,
        TenantContext $tenant,
        DesktopSettings $settings,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        $settings->putMany($request->validated());

        return back()->with('success', 'Error reporting preferences saved.');
    }

    public function download(
        TenantContext $tenant,
        ErrorReportingService $errors,
    ): HttpResponse {
        $this->desktopBusiness($tenant);

        $bundle = $errors->diagnosticBundle();
        $filename = 'kospal-diagnostics-'.now()->format('Y-m-d-His').'.json';

        return response(
            json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
