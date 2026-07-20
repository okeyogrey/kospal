<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\GoodsReceivedNoteStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\GoodsReceived\StoreGoodsReceivedNoteRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\GoodsReceivedNote;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\GoodsReceivedNoteService;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceivedNoteController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', GoodsReceivedNote::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $allowedBranchIds = $resolver->allowedBranches($user, $membership, $business)->pluck('id')->all();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $notes = GoodsReceivedNote::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when(in_array($status, GoodsReceivedNoteStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'))
            ->with(['supplier:id,name', 'branch:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (GoodsReceivedNote $grn) => [
                'id' => $grn->id,
                'reference' => $grn->reference,
                'status' => $grn->status->value,
                'supplier_name' => $grn->supplier?->name,
                'branch_name' => $grn->branch?->name,
                'items_count' => $grn->items_count,
                'created_by_name' => $grn->creator?->name,
                'created_at' => $grn->created_at?->toIso8601String(),
                'posted_at' => $grn->posted_at?->toIso8601String(),
            ]);

        return Inertia::render('goods-received/index', [
            'notes' => $notes,
            'statuses' => GoodsReceivedNoteStatus::values(),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, GoodsReceivedNoteStatus::values(), true) ? $status : null,
            ],
        ]);
    }

    public function create(Request $request, TenantContext $tenant, ResolvesTenant $resolver, FeatureFlagService $features): Response
    {
        $this->authorize('create', GoodsReceivedNote::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $purchaseOrderId = $request->integer('purchase_order_id') ?: null;
        $purchaseOrder = null;

        if ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::query()
                ->forBusiness($business)
                ->whereKey($purchaseOrderId)
                ->with(['items.product:id,name,sku', 'supplier:id,name'])
                ->first();
        }

        $openOrders = PurchaseOrder::query()
            ->forBusiness($business)
            ->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])
            ->whereIn('branch_id', $allowedBranches->pluck('id'))
            ->with('supplier:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'supplier_id', 'branch_id', 'status']);

        return Inertia::render('goods-received/create', [
            'suppliers' => Supplier::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $allowedBranches->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'products' => Product::query()->forBusiness($business)->active()->orderBy('name')->get(['id', 'name', 'sku', 'cost_price']),
            'purchaseOrders' => $openOrders->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'reference' => $po->reference,
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier?->name,
                'branch_id' => $po->branch_id,
                'status' => $po->status->value,
            ]),
            'selectedPurchaseOrder' => $purchaseOrder ? [
                'id' => $purchaseOrder->id,
                'reference' => $purchaseOrder->reference,
                'supplier_id' => $purchaseOrder->supplier_id,
                'branch_id' => $purchaseOrder->branch_id,
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'quantity_ordered' => $item->quantity_ordered,
                    'quantity_received' => $item->quantity_received,
                    'quantity_outstanding' => $item->quantityOutstanding(),
                    'unit_cost' => $item->unit_cost,
                ])->values(),
            ] : null,
            'defaultBranchId' => $tenant->branchId() ?? $allowedBranches->first()?->id,
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StoreGoodsReceivedNoteRequest $request,
        TenantContext $tenant,
        GoodsReceivedNoteService $grns,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $grn = $grns->create($business, $request->validated(), $request->user());

        return redirect()
            ->route('goods-received.show', $grn)
            ->with('success', 'Goods received note created.');
    }

    public function show(GoodsReceivedNote $goodsReceivedNote, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $goodsReceivedNote);
        $features->assertHasFeature($goodsReceivedNote->business, Features::PURCHASE_ORDERS);

        $goodsReceivedNote->load([
            'items.product:id,name,sku',
            'supplier:id,name',
            'branch:id,name',
            'purchaseOrder:id,reference',
            'creator:id,name',
            'poster:id,name',
            'stockMovements.product:id,name',
            'stockMovements.branch:id,name',
            'stockMovements.user:id,name',
            'supplierInvoice:id,reference,status',
        ]);

        $activity = AuditLog::query()
            ->where('auditable_type', $goodsReceivedNote->getMorphClass())
            ->where('auditable_id', $goodsReceivedNote->id)
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

        return Inertia::render('goods-received/show', [
            'note' => [
                'id' => $goodsReceivedNote->id,
                'reference' => $goodsReceivedNote->reference,
                'status' => $goodsReceivedNote->status->value,
                'supplier_name' => $goodsReceivedNote->supplier?->name,
                'branch_name' => $goodsReceivedNote->branch?->name,
                'purchase_order_reference' => $goodsReceivedNote->purchaseOrder?->reference,
                'invoice_id' => $goodsReceivedNote->supplierInvoice?->id,
                'invoice_reference' => $goodsReceivedNote->supplierInvoice?->reference,
                'notes' => $goodsReceivedNote->notes,
                'created_by_name' => $goodsReceivedNote->creator?->name,
                'posted_by_name' => $goodsReceivedNote->poster?->name,
                'created_at' => $goodsReceivedNote->created_at?->toIso8601String(),
                'posted_at' => $goodsReceivedNote->posted_at?->toIso8601String(),
                'items' => $goodsReceivedNote->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                ])->values(),
                'movements' => $goodsReceivedNote->stockMovements->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'quantity_delta' => $m->quantity_delta,
                    'unit_cost' => $m->unit_cost,
                    'product_name' => $m->product?->name,
                    'branch_name' => $m->branch?->name,
                    'user_name' => $m->user?->name,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values(),
            ],
            'activity' => $activity,
            'permissions' => [
                'post' => $tenant->user()?->can('post', $goodsReceivedNote) ?? false,
                'cancel' => $tenant->user()?->can('cancel', $goodsReceivedNote) ?? false,
            ],
            'currency' => $tenant->business()?->currency,
        ]);
    }

    public function post(GoodsReceivedNote $goodsReceivedNote, Request $request, GoodsReceivedNoteService $grns): RedirectResponse
    {
        $this->authorize('post', $goodsReceivedNote);
        $createInvoice = $request->boolean('create_invoice', true);
        $grns->post($goodsReceivedNote, $request->user(), $createInvoice);

        return back()->with('success', 'Goods received note posted. Stock updated.');
    }

    public function cancel(GoodsReceivedNote $goodsReceivedNote, Request $request, GoodsReceivedNoteService $grns): RedirectResponse
    {
        $this->authorize('cancel', $goodsReceivedNote);
        $grns->cancel($goodsReceivedNote, $request->user());

        return back()->with('success', 'Goods received note cancelled.');
    }
}
