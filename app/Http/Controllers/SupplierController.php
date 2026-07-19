<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Suppliers\UpdateSupplierRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\SupplierService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->forBusiness($business)
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->with(['products:id,name,sku'])
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
                'is_active' => $supplier->is_active,
                'products_count' => $supplier->products_count,
                'product_ids' => $supplier->products->pluck('id')->all(),
                'products' => $supplier->products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ])->values()->all(),
            ]);

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'products' => Product::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        StoreSupplierRequest $request,
        TenantContext $tenant,
        SupplierService $suppliers,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $suppliers->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Supplier created.');
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier,
        SupplierService $suppliers,
    ): RedirectResponse {
        $suppliers->update($supplier, $request->validated(), $request->user());

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(
        Supplier $supplier,
        SupplierService $suppliers,
    ): RedirectResponse {
        $this->authorize('delete', $supplier);

        $suppliers->delete($supplier, request()->user());

        return back()->with('success', 'Supplier deleted.');
    }
}
