<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\SupplierInvoiceStatus;
use App\Http\Requests\SupplierInvoices\StoreSupplierInvoiceRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use App\Support\FeatureFlags\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierInvoiceController extends Controller
{
    public function index(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('viewAny', SupplierInvoice::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->integer('supplier_id') ?: null;

        $invoices = SupplierInvoice::query()
            ->forBusiness($business)
            ->when(in_array($status, SupplierInvoiceStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('supplier_invoice_number', 'like', '%'.$search.'%');
                });
            })
            ->with(['supplier:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupplierInvoice $invoice) => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'supplier_invoice_number' => $invoice->supplier_invoice_number,
                'status' => $invoice->status->value,
                'supplier_name' => $invoice->supplier?->name,
                'total' => $invoice->total,
                'total_formatted' => Money::format($invoice->total, $business->currency),
                'amount_paid' => $invoice->amount_paid,
                'amount_due' => $invoice->amountDue(),
                'created_at' => $invoice->created_at?->toIso8601String(),
            ]);

        return Inertia::render('supplier-invoices/index', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
            'statuses' => SupplierInvoiceStatus::values(),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, SupplierInvoiceStatus::values(), true) ? $status : null,
                'supplier_id' => $supplierId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function create(TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('create', SupplierInvoice::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        return Inertia::render('supplier-invoices/create', [
            'suppliers' => Supplier::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->forBusiness($business)->active()->orderBy('name')->get(['id', 'name', 'sku', 'cost_price']),
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StoreSupplierInvoiceRequest $request,
        TenantContext $tenant,
        SupplierInvoiceService $invoices,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $invoice = $invoices->create($business, $request->validated(), $request->user());

        return redirect()
            ->route('supplier-invoices.show', $invoice)
            ->with('success', 'Supplier invoice created.');
    }

    public function show(SupplierInvoice $supplierInvoice, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $supplierInvoice);
        $features->assertHasFeature($supplierInvoice->business, Features::PURCHASE_ORDERS);

        $supplierInvoice->load([
            'items.product:id,name,sku',
            'supplier:id,name',
            'branch:id,name',
            'creator:id,name',
            'poster:id,name',
            'paymentAllocations.supplierPayment',
        ]);

        $currency = $tenant->business()?->currency ?? 'KES';

        $activity = AuditLog::query()
            ->where('auditable_type', $supplierInvoice->getMorphClass())
            ->where('auditable_id', $supplierInvoice->id)
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

        return Inertia::render('supplier-invoices/show', [
            'invoice' => [
                'id' => $supplierInvoice->id,
                'reference' => $supplierInvoice->reference,
                'supplier_invoice_number' => $supplierInvoice->supplier_invoice_number,
                'status' => $supplierInvoice->status->value,
                'supplier_name' => $supplierInvoice->supplier?->name,
                'branch_name' => $supplierInvoice->branch?->name,
                'subtotal' => $supplierInvoice->subtotal,
                'tax_total' => $supplierInvoice->tax_total,
                'total' => $supplierInvoice->total,
                'total_formatted' => Money::format($supplierInvoice->total, $currency),
                'amount_paid' => $supplierInvoice->amount_paid,
                'amount_due' => $supplierInvoice->amountDue(),
                'invoice_date' => $supplierInvoice->invoice_date?->toDateString(),
                'due_date' => $supplierInvoice->due_date?->toDateString(),
                'notes' => $supplierInvoice->notes,
                'created_by_name' => $supplierInvoice->creator?->name,
                'posted_by_name' => $supplierInvoice->poster?->name,
                'created_at' => $supplierInvoice->created_at?->toIso8601String(),
                'posted_at' => $supplierInvoice->posted_at?->toIso8601String(),
                'items' => $supplierInvoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $item->line_total,
                    'line_total_formatted' => Money::format($item->line_total, $currency),
                ])->values(),
                'allocations' => $supplierInvoice->paymentAllocations->map(fn ($alloc) => [
                    'id' => $alloc->id,
                    'amount' => $alloc->amount,
                    'amount_formatted' => Money::format($alloc->amount, $currency),
                    'payment_reference' => $alloc->supplierPayment?->reference,
                    'payment_id' => $alloc->supplier_payment_id,
                ])->values(),
            ],
            'activity' => $activity,
            'permissions' => [
                'post' => $tenant->user()?->can('post', $supplierInvoice) ?? false,
                'void' => $tenant->user()?->can('void', $supplierInvoice) ?? false,
            ],
            'currency' => $currency,
        ]);
    }

    public function post(SupplierInvoice $supplierInvoice, Request $request, SupplierInvoiceService $invoices): RedirectResponse
    {
        $this->authorize('post', $supplierInvoice);
        $invoices->post($supplierInvoice, $request->user());

        return back()->with('success', 'Supplier invoice posted.');
    }

    public function void(SupplierInvoice $supplierInvoice, Request $request, SupplierInvoiceService $invoices): RedirectResponse
    {
        $this->authorize('void', $supplierInvoice);
        $invoices->void($supplierInvoice, $request->user());

        return back()->with('success', 'Supplier invoice voided.');
    }
}
