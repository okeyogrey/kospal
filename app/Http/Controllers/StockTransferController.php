<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\StockTransferStatus;
use App\Http\Requests\StockTransfers\StoreStockTransferRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', StockTransfer::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::STOCK_TRANSFERS);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $status = $request->query('status');
        $sourceBranchId = $request->integer('source_branch_id') ?: null;
        $destinationBranchId = $request->integer('destination_branch_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        if ($sourceBranchId !== null && ! in_array($sourceBranchId, $allowedBranchIds, true)) {
            abort(403);
        }

        if ($destinationBranchId !== null && ! in_array($destinationBranchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $transfers = StockTransfer::query()
            ->forBusiness($business)
            ->where(function ($query) use ($allowedBranchIds): void {
                $query->whereIn('source_branch_id', $allowedBranchIds)
                    ->orWhereIn('destination_branch_id', $allowedBranchIds);
            })
            ->when(
                in_array($status, StockTransferStatus::values(), true),
                fn ($q) => $q->where('status', $status),
            )
            ->when($sourceBranchId, fn ($q) => $q->where('source_branch_id', $sourceBranchId))
            ->when($destinationBranchId, fn ($q) => $q->where('destination_branch_id', $destinationBranchId))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->with([
                'sourceBranch:id,business_id,name',
                'destinationBranch:id,business_id,name',
                'creator:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (StockTransfer $transfer) => $this->listPayload($transfer));

        return Inertia::render('transfers/index', [
            'transfers' => $transfers,
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'statuses' => StockTransferStatus::values(),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, StockTransferStatus::values(), true) ? $status : null,
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
            ],
        ]);
    }

    public function create(
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('create', StockTransfer::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);
        $features->assertHasFeature($business, Features::STOCK_TRANSFERS);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $balances = InventoryBalance::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->where('quantity', '>', 0)
            ->with(['product:id,name,sku', 'branch:id,name'])
            ->get()
            ->map(fn (InventoryBalance $balance) => [
                'branch_id' => $balance->branch_id,
                'product_id' => $balance->product_id,
                'product_name' => $balance->product?->name,
                'sku' => $balance->product?->sku,
                'quantity' => $balance->quantity,
            ])
            ->values();

        return Inertia::render('transfers/create', [
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'products' => Product::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'stockByBranch' => $balances,
            'defaultSourceBranchId' => $tenant->branch()?->id,
        ]);
    }

    public function store(
        StoreStockTransferRequest $request,
        TenantContext $tenant,
        StockTransferService $transfers,
        FeatureFlagService $features,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::STOCK_TRANSFERS);

        $transfer = $transfers->create(
            business: $business,
            data: $request->validated(),
            actor: $request->user(),
        );

        return redirect()
            ->route('stock-transfers.show', $transfer)
            ->with('success', 'Transfer draft created.');
    }

    public function show(
        StockTransfer $stockTransfer,
        TenantContext $tenant,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('view', $stockTransfer);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::STOCK_TRANSFERS);

        $stockTransfer->load([
            'sourceBranch:id,business_id,name',
            'destinationBranch:id,business_id,name',
            'items.product:id,name,sku',
            'creator:id,name',
            'dispatcher:id,name',
            'receiver:id,name',
            'canceller:id,name',
            'stockMovements' => fn ($q) => $q
                ->with(['branch:id,name', 'product:id,name', 'user:id,name'])
                ->orderBy('created_at'),
        ]);

        $activity = AuditLog::query()
            ->forBusiness($business)
            ->where('auditable_type', $stockTransfer->getMorphClass())
            ->where('auditable_id', $stockTransfer->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_name' => $log->user?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('transfers/show', [
            'transfer' => $this->detailPayload($stockTransfer),
            'activity' => $activity,
            'permissions' => [
                'dispatch' => $tenant->user()?->can('dispatch', $stockTransfer) ?? false,
                'receive' => $tenant->user()?->can('receive', $stockTransfer) ?? false,
                'cancel' => $tenant->user()?->can('cancel', $stockTransfer) ?? false,
            ],
        ]);
    }

    public function dispatch(
        StockTransfer $stockTransfer,
        Request $request,
        StockTransferService $transfers,
    ): RedirectResponse {
        $this->authorize('dispatch', $stockTransfer);

        $transfers->dispatch($stockTransfer, $request->user());

        return back()->with('success', 'Transfer dispatched. Stock removed from source branch.');
    }

    public function receive(
        StockTransfer $stockTransfer,
        Request $request,
        StockTransferService $transfers,
    ): RedirectResponse {
        $this->authorize('receive', $stockTransfer);

        $transfers->receive($stockTransfer, $request->user());

        return back()->with('success', 'Transfer received. Stock added to destination branch.');
    }

    public function cancel(
        StockTransfer $stockTransfer,
        Request $request,
        StockTransferService $transfers,
    ): RedirectResponse {
        $this->authorize('cancel', $stockTransfer);

        $transfers->cancel($stockTransfer, $request->user());

        return back()->with('success', 'Transfer cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(StockTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'reference' => $transfer->reference,
            'status' => $transfer->status->value,
            'source_branch_id' => $transfer->source_branch_id,
            'source_branch_name' => $transfer->sourceBranch?->name,
            'destination_branch_id' => $transfer->destination_branch_id,
            'destination_branch_name' => $transfer->destinationBranch?->name,
            'items_count' => $transfer->items_count ?? $transfer->items->count(),
            'notes' => $transfer->notes,
            'created_by_name' => $transfer->creator?->name,
            'created_at' => $transfer->created_at?->toIso8601String(),
            'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
            'received_at' => $transfer->received_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPayload(StockTransfer $transfer): array
    {
        return [
            ...$this->listPayload($transfer),
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'quantity' => $item->quantity,
            ])->values(),
            'created_by_name' => $transfer->creator?->name,
            'dispatched_by_name' => $transfer->dispatcher?->name,
            'received_by_name' => $transfer->receiver?->name,
            'cancelled_by_name' => $transfer->canceller?->name,
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
            'movements' => $transfer->stockMovements->map(fn ($movement) => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'quantity_delta' => $movement->quantity_delta,
                'quantity_before' => $movement->quantity_before,
                'quantity_after' => $movement->quantity_after,
                'branch_name' => $movement->branch?->name,
                'product_name' => $movement->product?->name,
                'user_name' => $movement->user?->name,
                'note' => $movement->note,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
