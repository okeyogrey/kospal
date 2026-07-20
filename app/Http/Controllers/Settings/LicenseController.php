<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\FeatureFlagService;
use App\Contracts\LicensingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Licenses\ActivateOfflineLicenseRequest;
use App\Http\Requests\Licenses\ActivateOnlineLicenseRequest;
use App\Services\LicenseService;
use App\Support\Deployment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function edit(
        TenantContext $tenant,
        FeatureFlagService $features,
        LicensingService $licensing,
        LicenseService $licenses,
    ): Response|RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $licensing->refreshStatus($business);
        $status = $licenses->statusPayload($business);

        $plans = collect(config('kospal.plans'))
            ->map(fn (array $plan, string $key) => [
                'key' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'] ?? '',
                'max_branches' => $plan['max_branches'],
                'max_staff' => $plan['max_staff'],
                'features' => $plan['features'],
            ])
            ->values()
            ->all();

        return Inertia::render('settings/license', [
            'license' => $status,
            'limits' => $features->limitsPayload($business),
            'plans' => $plans,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
        ]);
    }

    public function activateOnline(
        ActivateOnlineLicenseRequest $request,
        TenantContext $tenant,
        LicenseService $licenses,
    ): RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $licenses->activateOnline($business, $request->user(), $request->validated());

        return back()->with('success', 'License activated successfully.');
    }

    public function activateOffline(
        ActivateOfflineLicenseRequest $request,
        TenantContext $tenant,
        LicenseService $licenses,
    ): RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $licenses->activateOffline($business, $request->user(), $request->validated());

        return back()->with('success', 'Offline license activated successfully.');
    }
}
