<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionRequestStatus;
use App\Http\Requests\Subscriptions\StoreSubscriptionRequestRequest;
use App\Models\PlatformSetting;
use App\Models\SubscriptionRequest;
use App\Services\SubscriptionService;
use App\Support\Plans\PlanLimitChecker;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(TenantContext $tenant, PlanLimitChecker $limits): Response
    {
        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $requests = SubscriptionRequest::query()
            ->forBusiness($business)
            ->with(['reviewedBy:id,name'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SubscriptionRequest $request) => [
                'id' => $request->id,
                'requested_plan' => $request->requested_plan->value,
                'current_plan' => $request->current_plan->value,
                'status' => $request->status->value,
                'notes' => $request->notes,
                'transaction_code' => $request->transaction_code,
                'reviewer_notes' => $request->reviewer_notes,
                'reviewed_by' => $request->reviewedBy?->name,
                'created_at' => $request->created_at?->toIso8601String(),
                'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            ]);

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

        return Inertia::render('subscription/index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'plan' => $business->plan->value,
                'subscription_status' => $business->subscription_status->value,
                'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
                'allows_write_access' => $business->allowsWriteAccess(),
            ],
            'limits' => [
                'max_branches' => $limits->maxBranches($business),
                'active_branches' => $limits->activeBranchCount($business),
                'max_staff' => $limits->maxStaff($business),
                'staff_seats' => $limits->staffSeatCount($business),
                'features' => $business->plan->config()['features'],
            ],
            'plans' => $plans,
            'payment_instructions' => PlatformSetting::paymentInstructions(),
            'requests' => $requests,
            'has_pending_request' => SubscriptionRequest::query()
                ->forBusiness($business)
                ->where('status', SubscriptionRequestStatus::Pending)
                ->exists(),
        ]);
    }

    public function storeRequest(
        StoreSubscriptionRequestRequest $request,
        TenantContext $tenant,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $subscriptions->submitRequest($business, $request->user(), $request->validated());

        return back()->with('success', 'Subscription request submitted. We will review your payment shortly.');
    }
}
