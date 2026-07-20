<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\StockCountStatus;
use App\Http\Requests\StockCounts\RecordStockCountRequest;
use App\Http\Requests\StockCounts\StoreStockCountRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\StockCount;
use App\Services\StockCountService;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', StockCount::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::STOCK_COUNTS);

        $allowedBranchIds = $resolver->allowedBranches($user, $membership, $business)->pluck('id')->all();
        $status = $request->query('status');
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $counts = StockCount::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when(in_array($status, StockCountStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['branch:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (StockCount $count) => [
                'id' => $count->id,
                'reference' => $count->reference,
                'status' => $count->status->value,
                'branch_name' => $count->branch?->name,
                'items_count' => $count->items_count,
                'created_by_name' => $count->creator?->name,
                'created_at' => $count->created_at?->toIso8601String(),
                'completed_at' => $count->completed_at?->toIso8601String(),
            ]);

        return Inertia::render('stock-counts/index', [
            'counts' => $counts,
            'branches' => Branch::query()->forBusiness($business)->whereIn('id', $allowedBranchIds)->orderBy('name')->get(['id', 'name']),
            'statuses' => StockCountStatus::values(),
            'filters' => [
                'status' => in_array($status, StockCountStatus::values(), true) ? $status : null,
                'branch_id' => $branchId,
            ],
        ]);
    }

    public function create(TenantContext $tenant, ResolvesTenant $resolver, FeatureFlagService $features): Response
    {
        $this->authorize('create', StockCount::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::STOCK_COUNTS);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);

        return Inertia::render('stock-counts/create', [
            'branches' => $allowedBranches->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'defaultBranchId' => $tenant->branchId() ?? $allowedBranches->first()?->id,
        ]);
    }

    public function store(
        StoreStockCountRequest $request,
        TenantContext $tenant,
        StockCountService $counts,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $count = $counts->create($business, $request->validated(), $request->user());

        return redirect()
            ->route('stock-counts.show', $count)
            ->with('success', 'Stock count created.');
    }

    public function show(StockCount $stockCount, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $stockCount);
        $features->assertHasFeature($stockCount->business, Features::STOCK_COUNTS);

        $stockCount->load([
            'items.product:id,name,sku',
            'branch:id,name',
            'creator:id,name',
            'completer:id,name',
            'stockMovements.product:id,name',
            'stockMovements.user:id,name',
        ]);

        $activity = AuditLog::query()
            ->where('auditable_type', $stockCount->getMorphClass())
            ->where('auditable_id', $stockCount->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_name' => $log->user?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('stock-counts/show', [
            'count' => [
                'id' => $stockCount->id,
                'reference' => $stockCount->reference,
                'status' => $stockCount->status->value,
                'branch_name' => $stockCount->branch?->name,
                'notes' => $stockCount->notes,
                'created_by_name' => $stockCount->creator?->name,
                'completed_by_name' => $stockCount->completer?->name,
                'created_at' => $stockCount->created_at?->toIso8601String(),
                'completed_at' => $stockCount->completed_at?->toIso8601String(),
                'items' => $stockCount->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'system_quantity' => $item->system_quantity,
                    'counted_quantity' => $item->counted_quantity,
                    'variance' => $item->variance,
                ])->values(),
                'movements' => $stockCount->stockMovements->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'quantity_delta' => $m->quantity_delta,
                    'product_name' => $m->product?->name,
                    'user_name' => $m->user?->name,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values(),
            ],
            'activity' => $activity,
            'permissions' => [
                'update' => $tenant->user()?->can('update', $stockCount) ?? false,
                'complete' => $tenant->user()?->can('complete', $stockCount) ?? false,
                'cancel' => $tenant->user()?->can('cancel', $stockCount) ?? false,
            ],
        ]);
    }

    public function start(StockCount $stockCount, Request $request, StockCountService $counts): RedirectResponse
    {
        $this->authorize('update', $stockCount);
        $counts->start($stockCount, $request->user());

        return back()->with('success', 'Stock count started.');
    }

    public function record(RecordStockCountRequest $request, StockCount $stockCount, StockCountService $counts): RedirectResponse
    {
        $this->authorize('update', $stockCount);
        $counts->recordCounts($stockCount, $request->validated('counts'), $request->user());

        return back()->with('success', 'Counts saved.');
    }

    public function complete(StockCount $stockCount, Request $request, StockCountService $counts): RedirectResponse
    {
        $this->authorize('complete', $stockCount);
        $counts->complete($stockCount, $request->user());

        return back()->with('success', 'Stock count completed. Variances posted.');
    }

    public function cancel(StockCount $stockCount, Request $request, StockCountService $counts): RedirectResponse
    {
        $this->authorize('cancel', $stockCount);
        $counts->cancel($stockCount, $request->user());

        return back()->with('success', 'Stock count cancelled.');
    }
}
