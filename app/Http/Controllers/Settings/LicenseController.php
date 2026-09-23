<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\FeatureFlagService;
use App\Contracts\LicensingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Licenses\ActivateOfflineLicenseRequest;
use App\Http\Requests\Licenses\ActivateOnlineLicenseRequest;
use App\Http\Requests\Licenses\StoreLicenseEditionRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Services\LicenseService;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Support\Deployment;
use App\Support\Plans\PlanCatalog;
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
        SubscriptionService $subscriptions,
    ): Response|RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $licensing->refreshStatus($business);
        $status = $licenses->statusPayload($business);
        $quote = app(ReferralService::class)->quote($business);

        $branches = Branch::query()
            ->forBusiness($business)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'plan_paused_max_branches']);

        return Inertia::render('settings/license', [
            'license' => $status,
            'limits' => $features->limitsPayload($business),
            'plans' => PlanCatalog::cards($business->plan, $business->currency, $quote['discount_percent']),
            'pricing' => $quote,
            'branches' => $branches->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_active' => $branch->is_active,
                'plan_paused' => $branch->isPlanPaused(),
            ])->values()->all(),
            'payment_instructions' => PlatformSetting::paymentInstructions(),
            'requests' => $subscriptions->recentRequestsPayload($business),
            'has_pending_request' => $subscriptions->hasPendingRequest($business),
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
        ]);
    }

    public function storeEditionRequest(
        StoreLicenseEditionRequest $request,
        TenantContext $tenant,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $subscriptions->submitRequest($business, $request->user(), $request->validated());

        return back()->with(
            'success',
            'Edition request saved. Send the copied details with your payment proof. Features unlock only after you activate a matching license key.',
        );
    }

    public function activateOnline(
        ActivateOnlineLicenseRequest $request,
        TenantContext $tenant,
        LicenseService $licenses,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $pausedBefore = $this->planPausedCount($business);
        $activated = $licenses->activateOnline($business, $request->user(), $request->validated());
        $subscriptions->fulfillPendingForEdition(
            $activated,
            $activated->plan,
            $request->user(),
        );

        return back()->with('success', $this->activationSuccessMessage($this->planPausedCount($business) - $pausedBefore));
    }

    public function activateOffline(
        ActivateOfflineLicenseRequest $request,
        TenantContext $tenant,
        LicenseService $licenses,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $pausedBefore = $this->planPausedCount($business);
        $activated = $licenses->activateOffline($business, $request->user(), $request->validated());
        $subscriptions->fulfillPendingForEdition(
            $activated,
            $activated->plan,
            $request->user(),
        );

        return back()->with('success', $this->activationSuccessMessage($this->planPausedCount($business) - $pausedBefore));
    }

    protected function planPausedCount(Business $business): int
    {
        return Branch::query()
            ->forBusiness($business)
            ->whereNotNull('plan_paused_max_branches')
            ->count();
    }

    protected function activationSuccessMessage(int $paused): string
    {
        if ($paused <= 0) {
            return 'License activated successfully.';
        }

        return sprintf(
            'License activated. %d extra location%s paused. Data is kept, and paused shops cannot be swapped back later without upgrading.',
            $paused,
            $paused === 1 ? ' was' : 's were',
        );
    }
}
