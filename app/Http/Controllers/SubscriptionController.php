<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Contracts\LicensingService;
use App\Http\Requests\Subscriptions\StoreSubscriptionRequestRequest;
use App\Models\PlatformSetting;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Support\Deployment;
use App\Support\Plans\PlanCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(
        TenantContext $tenant,
        FeatureFlagService $features,
        LicensingService $licensing,
        SubscriptionService $subscriptions,
    ): Response|RedirectResponse {
        if (Deployment::isDesktop()) {
            return redirect()->route('license.edit');
        }

        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $licensing->refreshStatus($business);
        $quote = app(ReferralService::class)->quote($business);

        return Inertia::render('subscription/index', [
            'mode' => Deployment::mode(),
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'plan' => $licensing->plan($business)->value,
                'subscription_status' => $licensing->status($business)->value,
                'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
                'allows_write_access' => $licensing->allowsWriteAccess($business),
            ],
            'limits' => $features->limitsPayload($business),
            'plans' => PlanCatalog::cards($business->plan, $business->currency, $quote['discount_percent']),
            'pricing' => $quote,
            'payment_instructions' => PlatformSetting::paymentInstructions(),
            'requests' => $subscriptions->recentRequestsPayload($business),
            'has_pending_request' => $subscriptions->hasPendingRequest($business),
        ]);
    }

    public function storeRequest(
        StoreSubscriptionRequestRequest $request,
        TenantContext $tenant,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        abort_if(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $subscriptions->submitRequest($business, $request->user(), $request->validated());

        return back()->with('success', 'Edition request submitted. Features unlock after a platform admin verifies payment.');
    }
}
