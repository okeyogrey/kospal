<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Customer::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $customers = Customer::query()
            ->forBusiness($business)
            ->search($search !== '' ? $search : null)
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('sales')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'is_active' => $customer->is_active,
                'sales_count' => $customer->sales_count,
            ]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : null,
            ],
            'permissions' => [
                'create' => $request->user()?->can('create', Customer::class) ?? false,
                'manage' => $tenant->role()?->canManageCustomers() ?? false,
            ],
        ]);
    }

    public function search(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->forBusiness($business)
            ->active()
            ->search($search !== '' ? $search : null)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email']);

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
