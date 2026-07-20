<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPrinter;
use App\Contracts\FeatureFlagService;
use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Requests\Sales\CompleteSaleRequest;
use App\Http\Requests\Sales\HoldSaleRequest;
use App\Http\Requests\Sales\PartialReturnRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Support\FeatureFlags\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): Response {
        $this->authorize('viewAny', Sale::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $branchId = $request->integer('branch_id') ?: null;
        $cashierId = $request->integer('cashier_id') ?: null;
        $customerId = $request->integer('customer_id') ?: null;
        $paymentMethod = $request->query('payment_method');
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $sales = Sale::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->where('status', '!=', SaleStatus::Held)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($cashierId, fn ($q) => $q->where('cashier_id', $cashierId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when(
                in_array($paymentMethod, PaymentMethod::values(), true),
                fn ($q) => $q->where('payment_method', $paymentMethod),
            )
            ->when(
                in_array($status, SaleStatus::values(), true) && $status !== SaleStatus::Held->value,
                fn ($q) => $q->where('status', $status),
            )
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('sale_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('notes', 'like', $like);
                });
            })
            ->with([
                'branch:id,name',
                'cashier:id,name',
                'customer:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Sale $sale) => $this->listPayload($sale, $business->currency));

        $cashierIds = BusinessMembership::query()
            ->forBusiness($business)
            ->whereIn('role', [BusinessRole::Owner, BusinessRole::Manager, BusinessRole::Cashier])
            ->where('is_active', true)
            ->pluck('user_id');

        return Inertia::render('sales/index', [
            'sales' => $sales,
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'cashiers' => User::query()
                ->whereIn('id', $cashierIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'customers' => Customer::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name']),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values(),
            'statuses' => array_values(array_filter(
                SaleStatus::values(),
                static fn (string $value): bool => $value !== SaleStatus::Held->value,
            )),
            'filters' => [
                'search' => $search,
                'date_from' => is_string($dateFrom) ? $dateFrom : null,
                'date_to' => is_string($dateTo) ? $dateTo : null,
                'branch_id' => $branchId,
                'cashier_id' => $cashierId,
                'customer_id' => $customerId,
                'payment_method' => in_array($paymentMethod, PaymentMethod::values(), true) ? $paymentMethod : null,
                'status' => in_array($status, SaleStatus::values(), true) && $status !== SaleStatus::Held->value ? $status : null,
            ],
            'currency' => $business->currency,
            'permissions' => [
                'create' => $user->can('create', Sale::class),
                'void' => $tenant->role()?->canVoidSale() ?? false,
                'discount' => $tenant->role()?->canApplySaleDiscount() ?? false,
            ],
        ]);
    }

    public function pos(
        TenantContext $tenant,
        ResolvesTenant $resolver,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('create', Sale::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $branch = $tenant->branch();

        if ($branch !== null && ! $allowedBranches->contains('id', $branch->id)) {
            $branch = $allowedBranches->first();
        }

        $branch ??= $allowedBranches->first();
        abort_unless($branch, 403, 'A branch is required to use the POS.');

        $balances = InventoryBalance::query()
            ->forBusiness($business)
            ->where('branch_id', $branch->id)
            ->where('quantity', '>', 0)
            ->get(['product_id', 'quantity']);

        $productsById = Product::query()
            ->forBusiness($business)
            ->active()
            ->whereIn('id', $balances->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $stock = $balances
            ->map(function (InventoryBalance $balance) use ($productsById, $business) {
                $product = $productsById->get($balance->product_id);

                if (! $product instanceof Product) {
                    return null;
                }

                return $this->posProductPayload(
                    $product,
                    $balance->quantity,
                    $business->currency,
                );
            })
            ->filter()
            ->sortBy('name')
            ->values();

        $heldSales = Sale::query()
            ->forBusiness($business)
            ->where('branch_id', $branch->id)
            ->where('status', SaleStatus::Held)
            ->with(['items', 'cashier:id,name'])
            ->orderByDesc('held_at')
            ->limit(50)
            ->get()
            ->map(fn (Sale $sale) => $this->heldPayload($sale, $business->currency))
            ->values();

        return Inertia::render('sales/pos', [
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'activeBranchId' => $branch->id,
            'products' => $stock,
            'heldSales' => $heldSales,
            'customers' => Customer::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'phone', 'credit_enabled']),
            'paymentMethods' => $this->paymentMethodOptions($business, $features),
            'hasCustomerCredit' => $features->hasFeature($business, Features::CUSTOMER_CREDIT),
            'currency' => $business->currency,
            'permissions' => [
                'discount' => $user->can('applyDiscount', Sale::class),
                'negotiate' => $user->can('negotiatePrice', Sale::class),
                'approve_self' => $user->can('applyDiscount', Sale::class),
                'create_customer' => $user->can('create', Customer::class),
                'hold' => $user->can('hold', Sale::class),
                'negotiation_floor_percent' => (int) ($membership->negotiation_floor_percent ?? 100),
            ],
        ]);
    }

    public function searchProducts(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): JsonResponse {
        $this->authorize('create', Sale::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $branchId = $request->integer('branch_id') ?: $tenant->branchId();
        abort_unless($branchId, 422);

        $branch = Branch::query()->forBusiness($business)->whereKey($branchId)->firstOrFail();
        abort_unless(
            $resolver->allowedBranches($user, $membership, $business)->contains('id', $branch->id),
            403,
        );

        $search = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->forBusiness($business)
            ->active()
            ->search($search !== '' ? $search : null)
            ->orderBy('name')
            ->limit(40)
            ->get();

        $quantities = InventoryBalance::query()
            ->forBusiness($business)
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('quantity', 'product_id');

        return response()->json([
            'products' => $products->map(fn (Product $product) => $this->posProductPayload(
                $product,
                (int) ($quantities[$product->id] ?? 0),
                $business->currency,
            ))->values(),
        ]);
    }

    public function lookupBarcode(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): JsonResponse {
        $this->authorize('create', Sale::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $branchId = $request->integer('branch_id') ?: $tenant->branchId();
        $barcode = trim((string) $request->query('barcode', ''));
        abort_unless($branchId && $barcode !== '', 422);

        $branch = Branch::query()->forBusiness($business)->whereKey($branchId)->firstOrFail();
        abort_unless(
            $resolver->allowedBranches($user, $membership, $business)->contains('id', $branch->id),
            403,
        );

        $product = Product::query()
            ->forBusiness($business)
            ->active()
            ->barcode($barcode)
            ->first();

        if ($product === null) {
            return response()->json(['product' => null], 404);
        }

        $quantity = (int) InventoryBalance::query()
            ->forBusiness($business)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->value('quantity');

        return response()->json([
            'product' => $this->posProductPayload($product, $quantity, $business->currency),
        ]);
    }

    public function held(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): JsonResponse {
        $this->authorize('create', Sale::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $branchId = $request->integer('branch_id') ?: $tenant->branchId();
        abort_unless($branchId, 422);

        $branch = Branch::query()->forBusiness($business)->whereKey($branchId)->firstOrFail();
        abort_unless(
            $resolver->allowedBranches($user, $membership, $business)->contains('id', $branch->id),
            403,
        );

        $held = Sale::query()
            ->forBusiness($business)
            ->where('branch_id', $branch->id)
            ->where('status', SaleStatus::Held)
            ->with(['items', 'cashier:id,name'])
            ->orderByDesc('held_at')
            ->limit(50)
            ->get()
            ->map(fn (Sale $sale) => $this->heldPayload($sale, $business->currency))
            ->values();

        return response()->json(['held_sales' => $held]);
    }

    public function hold(
        HoldSaleRequest $request,
        TenantContext $tenant,
        SaleService $sales,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $sale = $sales->hold(
            business: $business,
            data: $request->validated(),
            actor: $request->user(),
        );

        return redirect()
            ->route('sales.pos')
            ->with('success', 'Sale '.$sale->sale_number.' held.');
    }

    public function resume(
        Sale $sale,
        TenantContext $tenant,
        SaleService $sales,
    ): JsonResponse {
        $this->authorize('view', $sale);
        abort_unless($sale->status->isHeld(), 422);

        $business = $tenant->business();
        abort_unless($business, 403);

        $resumed = $sales->resume($sale, request()->user());
        $resumed->loadMissing('items.product');
        $quantities = InventoryBalance::query()
            ->forBusiness($business)
            ->where('branch_id', $resumed->branch_id)
            ->whereIn('product_id', $resumed->items->pluck('product_id'))
            ->pluck('quantity', 'product_id');

        return response()->json([
            'sale' => [
                ...$this->heldPayload($resumed, $business->currency),
                'items' => $resumed->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'max_quantity' => max($item->quantity, (int) ($quantities[$item->product_id] ?? 0)),
                    'unit_price_minor' => $item->unit_price,
                    'list_unit_price_minor' => $item->list_unit_price,
                    'min_selling_price_minor' => (int) ($item->product?->min_selling_price ?? 0),
                    'is_negotiable' => (bool) ($item->product?->is_negotiable ?? true),
                ])->values(),
            ],
        ]);
    }

    public function discardHeld(
        Sale $sale,
        TenantContext $tenant,
        SaleService $sales,
    ): RedirectResponse {
        $this->authorize('view', $sale);
        abort_unless($sale->status->isHeld(), 422);

        $sales->discardHeld($sale, request()->user());

        return redirect()
            ->route('sales.pos')
            ->with('success', 'Held sale discarded.');
    }

    public function store(
        CompleteSaleRequest $request,
        TenantContext $tenant,
        SaleService $sales,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $sale = $sales->complete(
            business: $business,
            data: $request->validated(),
            actor: $request->user(),
        );

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'Sale '.$sale->sale_number.' completed.');
    }

    public function show(Sale $sale, TenantContext $tenant): Response
    {
        $this->authorize('view', $sale);
        abort_if($sale->status->isHeld(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $sale->load([
            'branch:id,business_id,name',
            'cashier:id,name',
            'approver:id,name',
            'customer:id,name,phone,email',
            'voider:id,name',
            'items',
            'payments.receiver:id,name',
            'returns.items',
            'stockMovements' => fn ($q) => $q
                ->with(['product:id,name', 'user:id,name'])
                ->orderBy('created_at'),
        ]);

        $activity = AuditLog::query()
            ->forBusiness($business)
            ->where('auditable_type', $sale->getMorphClass())
            ->where('auditable_id', $sale->id)
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

        return Inertia::render('sales/show', [
            'sale' => $this->detailPayload($sale, $business->currency),
            'activity' => $activity,
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values(),
            'permissions' => [
                'void' => $tenant->user()?->can('void', $sale) ?? false,
                'return' => $tenant->user()?->can('returnItems', $sale) ?? false,
                'reprint' => $tenant->user()?->can('reprint', $sale) ?? false,
                'approve_self' => $tenant->user()?->can('applyDiscount', Sale::class) ?? false,
            ],
        ]);
    }

    public function void(
        VoidSaleRequest $request,
        Sale $sale,
        SaleService $sales,
    ): RedirectResponse {
        $sales->void($sale, $request->validated(), $request->user());

        return back()->with('success', 'Sale voided and stock restored.');
    }

    public function returnItems(
        PartialReturnRequest $request,
        Sale $sale,
        SaleService $sales,
    ): RedirectResponse {
        $saleReturn = $sales->partialReturn($sale, $request->validated(), $request->user());

        return back()->with('success', 'Return '.$saleReturn->return_number.' recorded.');
    }

    public function reprint(
        Sale $sale,
        SaleService $sales,
    ): RedirectResponse {
        $this->authorize('reprint', $sale);

        $sales->recordReceiptReprint($sale, request()->user());

        return redirect()->route('sales.receipt', ['sale' => $sale, 'reprint' => 1]);
    }

    public function receipt(Sale $sale, TenantContext $tenant, DocumentPrinter $printer): HttpResponse
    {
        $this->authorize('view', $sale);

        $business = $tenant->business();
        abort_unless($business, 403);

        return $printer->receipt($sale, $business);
    }

    public function invoice(Sale $sale, TenantContext $tenant, DocumentPrinter $printer): HttpResponse
    {
        $this->authorize('view', $sale);

        $business = $tenant->business();
        abort_unless($business, 403);

        return $printer->invoice($sale, $business);
    }

    /**
     * @return array<string, mixed>
     */
    protected function posProductPayload(Product $product, int $quantity, string $currency): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'quantity' => $quantity,
            'selling_price_minor' => $product->selling_price,
            'cost_price_minor' => $product->cost_price,
            'min_selling_price_minor' => $product->min_selling_price,
            'is_negotiable' => (bool) $product->is_negotiable,
            'selling_price_formatted' => Money::format($product->selling_price, $currency),
            'min_selling_price_formatted' => Money::format($product->min_selling_price, $currency),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function heldPayload(Sale $sale, string $currency): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'held_label' => $sale->held_label,
            'held_at' => $sale->held_at?->toIso8601String(),
            'cashier_name' => $sale->cashier?->name,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer_name,
            'discount_amount_minor' => $sale->discount_amount,
            'notes' => $sale->notes,
            'item_count' => $sale->items->count(),
            'total_minor' => $sale->total,
            'total_formatted' => Money::format($sale->total, $currency),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Sale $sale, string $currency): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status->value,
            'payment_method' => $sale->payment_method?->value,
            'payment_method_label' => $sale->payment_method?->label(),
            'branch_id' => $sale->branch_id,
            'branch_name' => $sale->branch?->name,
            'cashier_id' => $sale->cashier_id,
            'cashier_name' => $sale->cashier?->name,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer?->name ?? $sale->customer_name,
            'items_count' => $sale->items_count ?? $sale->items->count(),
            'subtotal_minor' => $sale->subtotal,
            'subtotal_formatted' => Money::format($sale->subtotal, $currency),
            'discount_amount_minor' => $sale->discount_amount,
            'discount_amount_formatted' => Money::format($sale->discount_amount, $currency),
            'total_minor' => $sale->total,
            'total_formatted' => Money::format($sale->total, $currency),
            'amount_paid_minor' => $sale->amount_paid,
            'amount_paid_formatted' => Money::format($sale->amount_paid, $currency),
            'amount_due_minor' => $sale->amountDue(),
            'amount_due_formatted' => Money::format($sale->amountDue(), $currency),
            'created_at' => $sale->created_at?->toIso8601String(),
            'voided_at' => $sale->voided_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPayload(Sale $sale, string $currency): array
    {
        return [
            ...$this->listPayload($sale, $currency),
            'currency' => $sale->currency,
            'notes' => $sale->notes,
            'void_reason' => $sale->void_reason,
            'voided_by_name' => $sale->voider?->name,
            'approved_by_name' => $sale->approver?->name,
            'cash_tendered_minor' => $sale->cash_tendered,
            'cash_tendered_formatted' => Money::format($sale->cash_tendered, $currency),
            'change_given_minor' => $sale->change_given,
            'change_given_formatted' => Money::format($sale->change_given, $currency),
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->returned_quantity,
                'returnable_quantity' => $item->returnableQuantity(),
                'unit_price_minor' => $item->unit_price,
                'unit_price_formatted' => Money::format($item->unit_price, $currency),
                'list_unit_price_minor' => $item->list_unit_price,
                'list_unit_price_formatted' => Money::format($item->list_unit_price, $currency),
                'unit_cost_minor' => $item->unit_cost,
                'unit_cost_formatted' => Money::format($item->unit_cost, $currency),
                'line_total_minor' => $item->line_total,
                'line_total_formatted' => Money::format($item->line_total, $currency),
            ])->values(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'amount_minor' => $payment->amount,
                'amount_formatted' => Money::format($payment->amount, $currency),
                'tendered_amount_minor' => $payment->tendered_amount,
                'change_amount_minor' => $payment->change_amount,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'received_by_name' => $payment->receiver?->name,
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->values(),
            'returns' => $sale->returns->map(fn ($saleReturn) => [
                'id' => $saleReturn->id,
                'return_number' => $saleReturn->return_number,
                'total_formatted' => Money::format($saleReturn->total, $currency),
                'refund_method' => $saleReturn->refund_method->value,
                'reason' => $saleReturn->reason,
                'created_at' => $saleReturn->created_at?->toIso8601String(),
            ])->values(),
            'movements' => $sale->stockMovements->map(fn ($movement) => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'quantity_delta' => $movement->quantity_delta,
                'quantity_before' => $movement->quantity_before,
                'quantity_after' => $movement->quantity_after,
                'product_name' => $movement->product?->name,
                'user_name' => $movement->user?->name,
                'note' => $movement->note,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    protected function paymentMethodOptions($business, FeatureFlagService $features)
    {
        return collect(PaymentMethod::cases())
            ->reject(fn (PaymentMethod $method) => $method === PaymentMethod::Credit
                && ! $features->hasFeature($business, Features::CUSTOMER_CREDIT))
            ->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values();
    }
}
