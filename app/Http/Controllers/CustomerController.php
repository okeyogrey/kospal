<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\SaleStatus;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\CustomerAnalyticsService;
use App\Services\CustomerCreditService;
use App\Services\CustomerLedgerService;
use App\Services\CustomerService;
use App\Support\FeatureFlags\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomerController extends Controller
{
    public function index(Request $request, TenantContext $tenant, FeatureFlagService $features): Response
    {
        $this->authorize('viewAny', Customer::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $hasCreditFeature = $features->hasFeature($business, Features::CUSTOMER_CREDIT);

        $customers = Customer::query()
            ->forBusiness($business)
            ->search($search !== '' ? $search : null)
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($status === 'with_balance', fn ($q) => $q->where('credit_enabled', true))
            ->withCount('sales')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Customer $customer) use ($business, $hasCreditFeature): array {
                $row = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                    'notes' => $customer->notes,
                'is_active' => $customer->is_active,
                'sales_count' => $customer->sales_count,
                'credit_enabled' => $customer->credit_enabled,
                'payment_terms_days' => $customer->payment_terms_days,
            ];

                if ($hasCreditFeature && $customer->credit_enabled) {
                    $outstanding = app(CustomerCreditService::class)->outstandingBalance($customer);
                    $row['outstanding_balance'] = $outstanding;
                    $row['outstanding_balance_formatted'] = Money::format($outstanding, $business->currency);
                    $row['credit_limit'] = $customer->credit_limit;
                    $row['credit_limit_formatted'] = $customer->credit_limit !== null
                        ? Money::format($customer->credit_limit, $business->currency)
                        : null;
                }

                return $row;
            });

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['active', 'inactive', 'with_balance'], true) ? $status : null,
            ],
            'permissions' => [
                'create' => $request->user()?->can('create', Customer::class) ?? false,
                'manage' => $tenant->role()?->canManageCustomers() ?? false,
                'recordPayments' => $request->user()?->can('create', CustomerPayment::class) ?? false,
            ],
            'hasCreditFeature' => $hasCreditFeature,
            'currency' => $business->currency,
        ]);
    }

    public function show(
        Customer $customer,
        Request $request,
        TenantContext $tenant,
        FeatureFlagService $features,
        CustomerCreditService $credit,
        CustomerLedgerService $ledger,
        CustomerAnalyticsService $analytics,
    ): Response {
        $this->authorize('view', $customer);

        $business = $tenant->business();
        abort_unless($business, 403);

        $hasCreditFeature = $features->hasFeature($business, Features::CUSTOMER_CREDIT);
        $currency = $business->currency;
        $tab = in_array($request->query('tab'), ['ledger', 'payments', 'purchases', 'analytics'], true)
            ? $request->query('tab')
            : 'overview';

        $outstanding = $hasCreditFeature ? $credit->outstandingBalance($customer) : 0;
        $creditAvailable = $hasCreditFeature ? $credit->creditAvailable($customer) : null;
        $summary = $analytics->summarize($customer);

        $ledgerEntries = $hasCreditFeature
            ? $ledger->entriesForPeriod(
                $customer,
                $request->query('from'),
                $request->query('to'),
            )
            : [];

        $payments = CustomerPayment::query()
            ->forBusiness($business)
            ->where('customer_id', $customer->id)
            ->with('creator:id,name')
            ->orderByDesc('paid_at')
            ->limit(50)
            ->get()
            ->map(fn (CustomerPayment $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'amount_formatted' => Money::format($payment->amount, $currency),
                'paid_at' => $payment->paid_at?->toDateString(),
                'created_by_name' => $payment->creator?->name,
            ]);

        $purchases = Sale::query()
            ->forBusiness($business)
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'sale_number', 'total', 'amount_paid', 'created_at', 'branch_id'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => $sale->total,
                'total_formatted' => Money::format($sale->total, $currency),
                'amount_due' => $sale->amountDue(),
                'amount_due_formatted' => Money::format($sale->amountDue(), $currency),
                'created_at' => $sale->created_at?->toIso8601String(),
            ]);

        return Inertia::render('customers/show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'is_active' => $customer->is_active,
                'credit_enabled' => $customer->credit_enabled,
                'credit_limit' => $customer->credit_limit,
                'credit_limit_formatted' => $customer->credit_limit !== null
                    ? Money::format($customer->credit_limit, $currency)
                    : null,
                'payment_terms_days' => $customer->payment_terms_days,
                'outstanding_balance' => $outstanding,
                'outstanding_balance_formatted' => Money::format($outstanding, $currency),
                'credit_available' => $creditAvailable,
                'credit_available_formatted' => $creditAvailable !== null
                    ? Money::format($creditAvailable, $currency)
                    : null,
            ],
            'analytics' => [
                'lifetime_spend' => $summary['lifetime_spend'],
                'lifetime_spend_formatted' => Money::format($summary['lifetime_spend'], $currency),
                'purchase_count' => $summary['purchase_count'],
                'average_order_value' => $summary['average_order_value'],
                'average_order_value_formatted' => Money::format($summary['average_order_value'], $currency),
                'last_purchase_at' => $summary['last_purchase_at'],
                'most_purchased_products' => collect($summary['most_purchased_products'])->map(fn (array $row) => [
                    ...$row,
                    'total_spend_formatted' => Money::format($row['total_spend'], $currency),
                ])->values(),
            ],
            'ledger' => collect($ledgerEntries)->map(fn (array $entry) => [
                ...$entry,
                'amount_formatted' => Money::format($entry['amount'], $currency),
                'balance_after_formatted' => Money::format($entry['balance_after'], $currency),
            ])->values(),
            'payments' => $payments,
            'purchases' => $purchases,
            'tab' => $tab,
            'filters' => [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
            'permissions' => [
                'manage' => $tenant->role()?->canManageCustomers() ?? false,
                'recordPayments' => $request->user()?->can('create', CustomerPayment::class) ?? false,
            ],
            'hasCreditFeature' => $hasCreditFeature,
            'currency' => $currency,
        ]);
    }

    public function statement(
        Customer $customer,
        Request $request,
        TenantContext $tenant,
        FeatureFlagService $features,
        CustomerLedgerService $ledger,
        CustomerCreditService $credit,
    ): HttpResponse {
        $this->authorize('view', $customer);

        $business = $tenant->business();
        abort_unless($business, 403);
        $features->assertHasFeature($business, Features::CUSTOMER_CREDIT);

        $from = $request->query('from') ?? now()->subDays(30)->toDateString();
        $to = $request->query('to') ?? now()->toDateString();
        $currency = $business->currency;

        $entries = collect($ledger->entriesForPeriod($customer, $from, $to, 500))
            ->map(fn (array $entry) => [
                ...$entry,
                'amount_formatted' => Money::format($entry['amount'], $currency),
                'balance_after_formatted' => Money::format($entry['balance_after'], $currency),
            ]);

        $pdf = Pdf::loadView('customers.statement-pdf', [
            'customer' => $customer,
            'business' => $business,
            'currency' => $currency,
            'from' => $from,
            'to' => $to,
            'entries' => $entries,
            'outstanding_balance' => $credit->outstandingBalance($customer),
            'outstanding_balance_formatted' => Money::format($credit->outstandingBalance($customer), $currency),
        ])->setPaper('a4');

        $filename = sprintf('%s-statement-%s.pdf', str($customer->name)->slug(), $to);

        return $pdf->download($filename);
    }

    public function search(Request $request, TenantContext $tenant, FeatureFlagService $features): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));
        $hasCreditFeature = $features->hasFeature($business, Features::CUSTOMER_CREDIT);

        $customers = Customer::query()
            ->forBusiness($business)
            ->active()
            ->search($search !== '' ? $search : null)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email', 'credit_enabled', 'credit_limit']);

        if ($hasCreditFeature) {
            $creditService = app(CustomerCreditService::class);

            $customers = $customers->map(function (Customer $customer) use ($creditService, $business): array {
                $outstanding = $customer->credit_enabled
                    ? $creditService->outstandingBalance($customer)
                    : 0;
                $available = $customer->credit_enabled
                    ? $creditService->creditAvailable($customer)
                    : 0;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'credit_enabled' => $customer->credit_enabled,
                    'credit_limit' => $customer->credit_limit,
                    'outstanding_balance' => $outstanding,
                    'credit_available' => $available,
                    'outstanding_balance_formatted' => Money::format($outstanding, $business->currency),
                    'credit_available_formatted' => $available !== null
                        ? Money::format($available, $business->currency)
                        : null,
                ];
            });
        }

        return response()->json(['customers' => $customers]);
    }

    public function store(
        StoreCustomerRequest $request,
        TenantContext $tenant,
        CustomerService $customers,
    ): RedirectResponse|JsonResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $customer = $customers->create($business, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'credit_enabled' => $customer->credit_enabled,
                ],
            ], 201);
        }

        return back()->with('success', 'Customer created.');
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerService $customers,
    ): RedirectResponse {
        $customers->update($customer, $request->validated(), $request->user());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(
        Customer $customer,
        CustomerService $customers,
    ): RedirectResponse {
        $this->authorize('delete', $customer);

        $customers->delete($customer, request()->user());

        return back()->with('success', 'Customer deleted.');
    }
}
