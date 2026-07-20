<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Requests\CustomerPayments\StoreCustomerPaymentRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\CustomerPaymentService;
use App\Support\FeatureFlags\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function index(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('viewAny', CustomerPayment::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::CUSTOMER_CREDIT);

        $customerId = $request->integer('customer_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        $payments = CustomerPayment::query()
            ->forBusiness($business)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search !== '', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'))
            ->with(['customer:id,name', 'creator:id,name'])
            ->withCount('allocations')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CustomerPayment $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'customer_name' => $payment->customer?->name,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'amount_formatted' => Money::format($payment->amount, $business->currency),
                'paid_at' => $payment->paid_at?->toDateString(),
                'allocations_count' => $payment->allocations_count,
                'created_by_name' => $payment->creator?->name,
            ]);

        return Inertia::render('customer-payments/index', [
            'payments' => $payments,
            'customers' => Customer::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'customer_id' => $customerId,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function create(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('create', CustomerPayment::class);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::CUSTOMER_CREDIT);

        $customerId = $request->integer('customer_id') ?: null;

        $openSales = Sale::query()
            ->forBusiness($business)
            ->where('status', SaleStatus::Completed)
            ->whereColumn('amount_paid', '<', 'total')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer?->name,
                'total' => $sale->total,
                'amount_due' => $sale->amountDue(),
                'amount_due_formatted' => Money::format($sale->amountDue(), $business->currency),
                'created_at' => $sale->created_at?->toDateString(),
            ]);

        return Inertia::render('customer-payments/create', [
            'customers' => Customer::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'openSales' => $openSales,
            'methods' => collect(PaymentMethod::cases())
                ->reject(fn (PaymentMethod $m) => $m === PaymentMethod::Credit)
                ->map(fn (PaymentMethod $m) => [
                    'value' => $m->value,
                    'label' => $m->label(),
                ])->values(),
            'defaultCustomerId' => $customerId,
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StoreCustomerPaymentRequest $request,
        TenantContext $tenant,
        CustomerPaymentService $payments,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $payment = $payments->record($business, $request->validated(), $request->user());

        return redirect()
            ->route('customer-payments.show', $payment)
            ->with('success', 'Customer payment recorded.');
    }

    public function show(CustomerPayment $customerPayment, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('view', $customerPayment);
        $features->assertHasFeature($customerPayment->business, Features::CUSTOMER_CREDIT);

        $customerPayment->load([
            'customer:id,name',
            'creator:id,name',
            'allocations.sale:id,sale_number,total',
        ]);

        $currency = $tenant->business()?->currency ?? 'KES';

        $activity = AuditLog::query()
            ->where('auditable_type', $customerPayment->getMorphClass())
            ->where('auditable_id', $customerPayment->id)
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

        return Inertia::render('customer-payments/show', [
            'payment' => [
                'id' => $customerPayment->id,
                'reference' => $customerPayment->reference,
                'customer_name' => $customerPayment->customer?->name,
                'customer_id' => $customerPayment->customer_id,
                'method' => $customerPayment->method->value,
                'amount' => $customerPayment->amount,
                'amount_formatted' => Money::format($customerPayment->amount, $currency),
                'external_reference' => $customerPayment->external_reference,
                'notes' => $customerPayment->notes,
                'paid_at' => $customerPayment->paid_at?->toDateString(),
                'created_by_name' => $customerPayment->creator?->name,
                'created_at' => $customerPayment->created_at?->toIso8601String(),
                'allocations' => $customerPayment->allocations->map(fn ($alloc) => [
                    'id' => $alloc->id,
                    'sale_id' => $alloc->sale_id,
                    'sale_number' => $alloc->sale?->sale_number,
                    'amount' => $alloc->amount,
                    'amount_formatted' => Money::format($alloc->amount, $currency),
                ])->values(),
            ],
            'activity' => $activity,
            'currency' => $currency,
        ]);
    }
}
