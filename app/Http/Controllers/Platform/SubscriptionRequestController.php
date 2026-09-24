<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ApproveSubscriptionRequestRequest;
use App\Http\Requests\Platform\RejectSubscriptionRequestRequest;
use App\Models\Business;
use App\Models\SubscriptionRequest;
use App\Models\SyncAccount;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SubscriptionRequest::class);

        $status = $request->string('status')->toString();
        $allowed = [...SubscriptionRequestStatus::values(), 'all'];
        if ($status === '' || ! in_array($status, $allowed, true)) {
            $status = SubscriptionRequestStatus::Pending->value;
        }

        $requests = SubscriptionRequest::query()
            ->with([
                'business:id,name,plan,subscription_status,subscription_ends_at',
                'business.branches:id,business_id,name,is_active',
                'requestedBy:id,name,email',
                'reviewedBy:id,name',
            ])
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SubscriptionRequest $item) => [
                'id' => $item->id,
                'status' => $item->status->value,
                'requested_plan' => $item->requested_plan->value,
                'current_plan' => $item->current_plan->value,
                'transaction_code' => $item->transaction_code,
                'notes' => $item->notes,
                'reviewer_notes' => $item->reviewer_notes,
                'created_at' => $item->created_at?->toIso8601String(),
                'reviewed_at' => $item->reviewed_at?->toIso8601String(),
                'business' => [
                    'id' => $item->business->id,
                    'name' => $item->business->name,
                    'plan' => $item->business->plan->value,
                    'subscription_status' => $item->business->subscription_status->value,
                    'subscription_ends_at' => $item->business->subscription_ends_at?->toDateString(),
                    'branches' => $item->business->branches
                        ->sortBy('name')
                        ->map(fn ($branch) => [
                            'id' => $branch->id,
                            'name' => $branch->name,
                            'is_active' => $branch->is_active,
                        ])
                        ->values()
                        ->all(),
                ],
                'requested_by' => [
                    'name' => $item->requestedBy?->name,
                    'email' => $item->requestedBy?->email,
                ],
                'reviewed_by' => $item->reviewedBy?->name,
            ]);

        $businesses = Business::query()
            ->with([
                'owner:id,name,email',
                'branches:id,business_id,name,is_active',
            ])
            ->latest()
            ->limit(50)
            ->get();

        $businessIds = $businesses->pluck('id');
        $productCounts = DB::table('products')
            ->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, COUNT(*) as aggregate')
            ->groupBy('business_id')
            ->pluck('aggregate', 'business_id');
        $salesCounts = DB::table('sales')
            ->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, COUNT(*) as aggregate')
            ->groupBy('business_id')
            ->pluck('aggregate', 'business_id');
        $stockUnits = DB::table('inventory_balances')
            ->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, SUM(quantity) as aggregate')
            ->groupBy('business_id')
            ->pluck('aggregate', 'business_id');

        $accounts = Schema::hasTable('sync_accounts')
            ? SyncAccount::query()
                ->with('devices:id,sync_account_id,name,last_seen_at')
                ->get()
                ->keyBy(fn (SyncAccount $account): string => (string) $account->business_public_uuid)
            : collect();

        $businessPayload = $businesses->map(function (Business $business) use ($productCounts, $salesCounts, $stockUnits, $accounts): array {
            $account = $accounts->get((string) $business->public_uuid);

            return [
                'id' => $business->id,
                'name' => $business->name,
                'plan' => $business->plan->value,
                'subscription_status' => $business->subscription_status->value,
                'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
                'owner' => $business->owner?->only(['name', 'email']),
                'products_count' => (int) ($productCounts[$business->id] ?? 0),
                'sales_count' => (int) ($salesCounts[$business->id] ?? 0),
                'stock_units' => (int) ($stockUnits[$business->id] ?? 0),
                'computers' => $account instanceof SyncAccount
                    ? $account->devices
                        ->sortBy('name')
                        ->map(fn ($device): array => [
                            'name' => (string) $device->name,
                            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                        ])
                        ->values()
                        ->all()
                    : [],
                'branches' => $business->branches
                    ->sortBy('name')
                    ->map(fn ($branch) => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'is_active' => $branch->is_active,
                    ])
                    ->values()
                    ->all(),
            ];
        });

        return Inertia::render('platform/subscription-requests/index', [
            'requests' => $requests,
            'businesses' => $businessPayload,
            'filters' => [
                'status' => $status,
            ],
            'plans' => Plan::values(),
            'plan_max_branches' => collect(Plan::cases())
                ->mapWithKeys(fn (Plan $plan) => [$plan->value => $plan->maxBranches()])
                ->all(),
            'subscription_statuses' => SubscriptionStatus::values(),
            'default_subscription_days' => (int) config('kospal.default_subscription_days', 30),
        ]);
    }

    public function approve(
        ApproveSubscriptionRequestRequest $request,
        SubscriptionRequest $subscriptionRequest,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $subscriptions->approveRequest(
            $subscriptionRequest,
            $request->user(),
            $request->validated(),
        );

        return back()->with('success', 'Subscription request approved.');
    }

    public function reject(
        RejectSubscriptionRequestRequest $request,
        SubscriptionRequest $subscriptionRequest,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $subscriptions->rejectRequest(
            $subscriptionRequest,
            $request->user(),
            $request->validated(),
        );

        return back()->with('success', 'Subscription request rejected.');
    }
}
