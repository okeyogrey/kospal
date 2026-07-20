<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentReason;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Http\Requests\Inventory\StoreStockReceiptRequest;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\InventoryTimelineService;
use App\Services\InventoryValuationService;
use App\Support\Money\Money;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): Response {
        $this->authorize('viewAny', InventoryBalance::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $search = trim((string) $request->query('search', ''));
        $branchId = $request->integer('branch_id') ?: null;
        $status = $request->query('status');

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $searchQuery = InventoryBalance::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('product', function ($product) use ($search): void {
                    $product->search($search);
                });
            });

        $baseQuery = (clone $searchQuery)
            ->when($status === 'low_stock', fn ($q) => $q->lowStock())
            ->when($status === 'in_stock', function ($q): void {
                $q->where('quantity', '>', 0)
                    ->whereHas('product', function ($product): void {
                        $product->whereColumn('inventory_balances.quantity', '>', 'products.reorder_level');
                    });
            })
            ->when($status === 'out_of_stock', fn ($q) => $q->where('quantity', 0));

        $totalValueMinor = (int) (clone $baseQuery)
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->selectRaw('COALESCE(SUM(inventory_balances.quantity * products.cost_price), 0) as total_value')
            ->value('total_value');

        $statusCounts = [
            'in_stock' => (clone $searchQuery)
                ->where('quantity', '>', 0)
                ->whereHas('product', function ($product): void {
                    $product->whereColumn('inventory_balances.quantity', '>', 'products.reorder_level');
                })
                ->count(),
            'low_stock' => (clone $searchQuery)
                ->where('quantity', '>', 0)
                ->whereHas('product', function ($product): void {
                    $product->whereColumn('inventory_balances.quantity', '<=', 'products.reorder_level');
                })
                ->count(),
            'out_of_stock' => (clone $searchQuery)->where('quantity', 0)->count(),
        ];

        $balances = (clone $baseQuery)
            ->with([
                'branch:id,name',
                'product:id,name,sku,barcode,cost_price,reorder_level,is_active,category_id',
                'product.category:id,name',
                'product.suppliers:id,name,phone',
            ])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryBalance $balance) => $this->balancePayload($balance, $business->currency));

        return Inertia::render('inventory/index', [
            'balances' => $balances,
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'products' => Product::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode']),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'status' => in_array($status, ['low_stock', 'in_stock', 'out_of_stock'], true) ? $status : null,
            ],
            'summary' => [
                'total_value_minor' => $totalValueMinor,
                'total_value_formatted' => Money::format($totalValueMinor, $business->currency),
                'in_stock_count' => $statusCounts['in_stock'],
                'low_stock_count' => $statusCounts['low_stock'],
                'out_of_stock_count' => $statusCounts['out_of_stock'],
            ],
            'adjustmentReasons' => collect(StockAdjustmentReason::cases())
                ->map(fn (StockAdjustmentReason $reason) => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'allows_increase' => $reason->allowsIncrease(),
                    'allows_decrease' => $reason->allowsDecrease(),
                ])
                ->values()
                ->all(),
            'currency' => $business->currency,
        ]);
    }

    public function timeline(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        InventoryTimelineService $timeline,
    ): Response {
        $this->authorize('viewAny', InventoryBalance::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $branchId = $request->integer('branch_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        return Inertia::render('inventory/timeline', [
            'entries' => $timeline->forBusiness($business, $branchId, $productId),
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'products' => Product::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'filters' => [
                'branch_id' => $branchId,
                'product_id' => $productId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function valuation(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        InventoryValuationService $valuation,
    ): Response {
        $this->authorize('viewAny', InventoryBalance::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $branchId = $request->integer('branch_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $summary = $valuation->summarize($business, $branchId);

        return Inertia::render('inventory/valuation', [
            'summary' => $summary,
            'costHistory' => $valuation->costHistory($business, $productId),
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'products' => Product::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'filters' => [
                'branch_id' => $branchId,
                'product_id' => $productId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function lowStock(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): Response {
        $this->authorize('viewAny', InventoryBalance::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranchIds = $resolver
            ->allowedBranches($user, $membership, $business)
            ->pluck('id')
            ->all();

        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $alerts = InventoryBalance::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->lowStock()
            ->with([
                'branch:id,name',
                'product:id,name,sku,cost_price,reorder_level,is_active',
                'product.suppliers:id,name,phone,contact_name',
            ])
            ->orderBy('quantity')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryBalance $balance) => $this->balancePayload($balance, $business->currency));

        return Inertia::render('inventory/low-stock', [
            'alerts' => $alerts,
            'branches' => Branch::query()
                ->forBusiness($business)
                ->whereIn('id', $allowedBranchIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'branch_id' => $branchId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function storeReceiveStock(
        StoreStockReceiptRequest $request,
        TenantContext $tenant,
        InventoryService $inventory,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $data = $request->validated();
        $branch = Branch::query()->forBusiness($business)->whereKey($data['branch_id'])->firstOrFail();
        $product = Product::query()->forBusiness($business)->whereKey($data['product_id'])->firstOrFail();

        $this->authorize('receiveStock', [InventoryBalance::class, $branch]);

        $inventory->receiveStock(
            business: $business,
            branch: $branch,
            product: $product,
            quantity: (int) $data['quantity'],
            actor: $request->user(),
            note: $data['note'] ?? null,
            unitCost: isset($data['unit_cost']) ? (int) $data['unit_cost'] : null,
        );

        return back()->with('success', 'Stock received.');
    }

    public function storeAdjustment(
        StoreStockAdjustmentRequest $request,
        TenantContext $tenant,
        InventoryService $inventory,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $data = $request->validated();
        $branch = Branch::query()->forBusiness($business)->whereKey($data['branch_id'])->firstOrFail();
        $product = Product::query()->forBusiness($business)->whereKey($data['product_id'])->firstOrFail();
        $reason = StockAdjustmentReason::from($data['reason']);
        $quantity = abs((int) $data['quantity']);
        $delta = $data['direction'] === 'increase' ? $quantity : -$quantity;

        $this->authorize('adjust', [InventoryBalance::class, $branch]);

        $inventory->adjustStock(
            business: $business,
            branch: $branch,
            product: $product,
            quantityDelta: $delta,
            reason: $reason,
            note: $data['note'],
            actor: $request->user(),
        );

        return back()->with('success', 'Stock adjustment recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function balancePayload(InventoryBalance $balance, string $currency): array
    {
        $product = $balance->product;
        $value = $balance->quantity * (int) ($product?->cost_price ?? 0);
        $suppliers = $product?->relationLoaded('suppliers')
            ? $product->suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'contact_name' => $supplier->contact_name ?? null,
            ])->values()->all()
            : [];

        return [
            'id' => $balance->id,
            'branch_id' => $balance->branch_id,
            'branch_name' => $balance->branch?->name,
            'product_id' => $balance->product_id,
            'product_name' => $product?->name,
            'sku' => $product?->sku,
            'barcode' => $product?->barcode,
            'category_name' => $product?->category?->name,
            'quantity' => $balance->quantity,
            'reorder_level' => $product?->reorder_level ?? 0,
            'is_low_stock' => $product !== null && $balance->quantity <= $product->reorder_level,
            'is_active' => (bool) $product?->is_active,
            'value_minor' => $value,
            'value_formatted' => Money::format($value, $currency),
            'suppliers' => $suppliers,
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }
}
