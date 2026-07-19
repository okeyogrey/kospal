<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Requests\Sales\CompleteSaleRequest;
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
use App\Support\Money\Money;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
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
                in_array($status, SaleStatus::values(), true),
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
            'statuses' => SaleStatus::values(),
            'filters' => [
                'search' => $search,
                'date_from' => is_string($dateFrom) ? $dateFrom : null,
                'date_to' => is_string($dateTo) ? $dateTo : null,
                'branch_id' => $branchId,
                'cashier_id' => $cashierId,
                'customer_id' => $customerId,
                'payment_method' => in_array($paymentMethod, PaymentMethod::values(), true) ? $paymentMethod : null,
                'status' => in_array($status, SaleStatus::values(), true) ? $status : null,
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

        return Inertia::render('sales/pos', [
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'activeBranchId' => $branch->id,
            'products' => $stock,
            'customers' => Customer::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'phone']),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values(),
            'currency' => $business->currency,
            'permissions' => [
                'discount' => $user->can('applyDiscount', Sale::class),
                'create_customer' => $user->can('create', Customer::class),
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

        $business = $tenant->business();
        abort_unless($business, 403);

        $sale->load([
            'branch:id,business_id,name',
            'cashier:id,name',
            'customer:id,name,phone,email',
            'voider:id,name',
            'items',
            'payments.receiver:id,name',
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
            'permissions' => [
                'void' => $tenant->user()?->can('void', $sale) ?? false,
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

    public function receipt(Sale $sale, TenantContext $tenant): HttpResponse
    {
        $this->authorize('view', $sale);

        $business = $tenant->business();
        abort_unless($business, 403);

        $sale->load([
            'branch',
            'cashier:id,name',
            'customer:id,name,phone',
            'items',
            'payments',
            'business',
        ]);

        return response()
            ->view('sales.receipt', [
                'sale' => $sale,
                'business' => $business,
                'currency' => $sale->currency,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function invoice(Sale $sale, TenantContext $tenant): HttpResponse
    {
        $this->authorize('view', $sale);

        $business = $tenant->business();
        abort_unless($business, 403);

        $sale->load([
            'branch',
            'cashier:id,name',
            'customer:id,name,phone,email,address',
            'items',
            'payments',
        ]);

        $pdf = Pdf::loadView('sales.invoice-pdf', [
            'sale' => $sale,
            'business' => $business,
            'currency' => $sale->currency,
        ])->setPaper('a4');

        return $pdf->download($sale->sale_number.'-invoice.pdf');
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
            'selling_price_formatted' => Money::format($product->selling_price, $currency),
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
            'payment_method' => $sale->payment_method->value,
            'payment_method_label' => $sale->payment_method->label(),
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
                'unit_price_minor' => $item->unit_price,
                'unit_price_formatted' => Money::format($item->unit_price, $currency),
                'line_total_minor' => $item->line_total,
                'line_total_formatted' => Money::format($item->line_total, $currency),
            ])->values(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'amount_minor' => $payment->amount,
                'amount_formatted' => Money::format($payment->amount, $currency),
                'reference' => $payment->reference,
                'received_by_name' => $payment->receiver?->name,
                'created_at' => $payment->created_at?->toIso8601String(),
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
}
