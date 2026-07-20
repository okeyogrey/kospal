<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\PaymentMethod;
use App\Enums\SupplierInvoiceStatus;
use App\Http\Requests\SupplierPayments\StoreSupplierPaymentRequest;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use App\Support\FeatureFlags\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function index(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $supplierId = $request->integer('supplier_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        $payments = SupplierPayment::query()
            ->forBusiness($business)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search !== '', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'))
            ->with(['supplier:id,name', 'creator:id,name'])
            ->withCount('allocations')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupplierPayment $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'supplier_name' => $payment->supplier?->name,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'amount_formatted' => Money::format($payment->amount, $business->currency),
                'paid_at' => $payment->paid_at?->toDateString(),
                'allocations_count' => $payment->allocations_count,
                'created_by_name' => $payment->creator?->name,
            ]);

        return Inertia::render('supplier-payments/index', [
            'payments' => $payments,
            'suppliers' => Supplier::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'supplier_id' => $supplierId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function create(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('create', SupplierPayment::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::PURCHASE_ORDERS);

        $supplierId = $request->integer('supplier_id') ?: null;

        $openInvoices = SupplierInvoice::query()
            ->forBusiness($business)
            ->whereIn('status', [SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::PartiallyPaid])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->with('supplier:id,name')
            ->orderBy('due_date')
            ->get()
            ->map(fn (SupplierInvoice $invoice) => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'supplier_id' => $invoice->supplier_id,
                'supplier_name' => $invoice->supplier?->name,
                'total' => $invoice->total,
                'amount_due' => $invoice->amountDue(),
                'amount_due_formatted' => Money::format($invoice->amountDue(), $business->currency),
            ]);

        return Inertia::render('supplier-payments/create', [
            'suppliers' => Supplier::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'openInvoices' => $openInvoices,
            'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
            'defaultSupplierId' => $supplierId,
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StoreSupplierPaymentRequest $request,
        TenantContext $tenant,
        SupplierPaymentService $payments,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $payment = $payments->record($business, $request->validated(), $request->user());

        return redirect()
            ->route('supplier-payments.show', $payment)
            ->with('success', 'Supplier payment recorded.');
    }

    public function show(SupplierPayment $supplierPayment, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $supplierPayment);
        $features->assertHasFeature($supplierPayment->business, Features::PURCHASE_ORDERS);

        $supplierPayment->load([
            'supplier:id,name',
            'creator:id,name',
            'allocations.supplierInvoice:id,reference,status',
        ]);

        $currency = $tenant->business()?->currency ?? 'KES';

        $activity = AuditLog::query()
            ->where('auditable_type', $supplierPayment->getMorphClass())
            ->where('auditable_id', $supplierPayment->id)
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

        return Inertia::render('supplier-payments/show', [
            'payment' => [
                'id' => $supplierPayment->id,
                'reference' => $supplierPayment->reference,
                'supplier_name' => $supplierPayment->supplier?->name,
                'method' => $supplierPayment->method->value,
                'amount' => $supplierPayment->amount,
                'amount_formatted' => Money::format($supplierPayment->amount, $currency),
                'external_reference' => $supplierPayment->external_reference,
                'notes' => $supplierPayment->notes,
                'paid_at' => $supplierPayment->paid_at?->toDateString(),
                'created_by_name' => $supplierPayment->creator?->name,
                'created_at' => $supplierPayment->created_at?->toIso8601String(),
                'allocations' => $supplierPayment->allocations->map(fn ($alloc) => [
                    'id' => $alloc->id,
                    'invoice_id' => $alloc->supplier_invoice_id,
                    'invoice_reference' => $alloc->supplierInvoice?->reference,
                    'amount' => $alloc->amount,
                    'amount_formatted' => Money::format($alloc->amount, $currency),
                ])->values(),
            ],
            'activity' => $activity,
            'currency' => $currency,
        ]);
    }
}
