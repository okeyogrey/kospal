<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', PurchaseOrder::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $allowedBranchIds = $resolver->allowedBranches($user, $membership, $business)->pluck('id')->all();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->integer('supplier_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $orders = PurchaseOrder::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when(in_array($status, PurchaseOrderStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->with(['supplier:id,name', 'branch:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'supplier_name' => $order->supplier?->name,
                'branch_name' => $order->branch?->name,
                'items_count' => $order->items_count,
                'expected_at' => $order->expected_at?->toDateString(),
                'created_by_name' => $order->creator?->name,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('purchase-orders/index', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->forBusiness($business)->whereIn('id', $allowedBranchIds)->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseOrderStatus::values(),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, PurchaseOrderStatus::values(), true) ? $status : null,
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
            ],
        ]);
    }

    public function create(TenantContext $tenant, ResolvesTenant $resolver, FeatureFlagService $features): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);

        return Inertia::render('purchase-orders/create', [
            'suppliers' => Supplier::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $allowedBranches->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'products' => Product::query()->forBusiness($business)->active()->orderBy('name')->get(['id', 'name', 'sku', 'cost_price']),
            'defaultBranchId' => $tenant->branchId() ?? $allowedBranches->first()?->id,
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StorePurchaseOrderRequest $request,
        TenantContext $tenant,
        PurchaseOrderService $orders,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $order = $orders->create($business, $request->validated(), $request->user());

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $purchaseOrder);
        $features->assertHasFeature($purchaseOrder->business, Features::PURCHASE_ORDERS);

        $purchaseOrder->load([
            'items.product:id,name,sku',
            'supplier:id,name',
            'branch:id,name',
            'creator:id,name',
            'sender:id,name',
            'canceller:id,name',
        ]);

        $activity = AuditLog::query()
            ->where('auditable_type', $purchaseOrder->getMorphClass())
            ->where('auditable_id', $purchaseOrder->id)
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

        return Inertia::render('purchase-orders/show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'reference' => $purchaseOrder->reference,
                'status' => $purchaseOrder->status->value,
                'supplier_name' => $purchaseOrder->supplier?->name,
                'branch_name' => $purchaseOrder->branch?->name,
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'notes' => $purchaseOrder->notes,
                'created_by_name' => $purchaseOrder->creator?->name,
                'sent_by_name' => $purchaseOrder->sender?->name,
                'cancelled_by_name' => $purchaseOrder->canceller?->name,
                'created_at' => $purchaseOrder->created_at?->toIso8601String(),
                'sent_at' => $purchaseOrder->sent_at?->toIso8601String(),
                'cancelled_at' => $purchaseOrder->cancelled_at?->toIso8601String(),
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'quantity_ordered' => $item->quantity_ordered,
                    'quantity_received' => $item->quantity_received,
                    'unit_cost' => $item->unit_cost,
                ])->values(),
            ],
            'activity' => $activity,
            'permissions' => [
                'send' => $tenant->user()?->can('send', $purchaseOrder) ?? false,
                'cancel' => $tenant->user()?->can('cancel', $purchaseOrder) ?? false,
            ],
            'currency' => $tenant->business()?->currency,
        ]);
    }

    public function send(PurchaseOrder $purchaseOrder, Request $request, PurchaseOrderService $orders): RedirectResponse
    {
        $this->authorize('send', $purchaseOrder);
        $orders->send($purchaseOrder, $request->user());

        return back()->with('success', 'Purchase order sent.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, Request $request, PurchaseOrderService $orders): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);
        $orders->cancel($purchaseOrder, $request->user());

        return back()->with('success', 'Purchase order cancelled.');
    }
}
