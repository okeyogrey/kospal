<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use App\Support\Catalog\CatalogTaxonomy;
use App\Support\Money\Money;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Product::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');

        $products = Product::query()
            ->forBusiness($business)
            ->with(['category:id,name'])
            ->withCount('suppliers')
            ->search($search !== '' ? $search : null)
            ->when($categoryId, fn ($q) => $q->where('category_id', (int) $categoryId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $products->setCollection(
            $products->getCollection()->map(
                fn (Product $product) => $this->productPayload($product, $business->currency),
            ),
        );

        return Inertia::render('products/index', [
            'products' => $products,
            'taxonomy' => CatalogTaxonomy::forBusiness($business),
            'suppliers' => Supplier::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : null,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function show(
        Product $product,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): Response {
        $this->authorize('view', $product);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranchIds = $resolver
            ->allowedBranches($user, $membership, $business)
            ->pluck('id')
            ->all();

        $product->load([
            'category:id,name',
            'suppliers:id,name,phone',
            'inventoryBalances' => fn ($q) => $q
                ->whereIn('branch_id', $allowedBranchIds)
                ->with('branch:id,name'),
            'stockMovements' => fn ($q) => $q
                ->whereIn('branch_id', $allowedBranchIds)
                ->with(['branch:id,name', 'user:id,name'])
                ->latest('created_at')
                ->limit(50),
        ]);

        return Inertia::render('products/show', [
            'product' => [
                ...$this->productPayload($product, $business->currency),
                'description' => $product->description,
                'supplier_ids' => $product->suppliers->pluck('id')->all(),
                'suppliers' => $product->suppliers->map(fn (Supplier $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'phone' => $s->phone,
                ])->values(),
                'stock_by_branch' => $product->inventoryBalances->map(fn ($balance) => [
                    'id' => $balance->id,
                    'branch_id' => $balance->branch_id,
                    'branch_name' => $balance->branch?->name,
                    'quantity' => $balance->quantity,
                    'is_low_stock' => $balance->quantity <= $product->reorder_level,
                    'value_minor' => $balance->quantity * $product->cost_price,
                    'value_formatted' => Money::format(
                        $balance->quantity * $product->cost_price,
                        $business->currency,
                    ),
                ])->values(),
                'movements' => $product->stockMovements->map(fn ($movement) => [
                    'id' => $movement->id,
                    'type' => $movement->type->value,
                    'type_label' => $movement->type->label(),
                    'quantity_delta' => $movement->quantity_delta,
                    'quantity_before' => $movement->quantity_before,
                    'quantity_after' => $movement->quantity_after,
                    'reason' => $movement->reason?->value,
                    'note' => $movement->note,
                    'branch_name' => $movement->branch?->name,
                    'user_name' => $movement->user?->name,
                    'created_at' => $movement->created_at?->toIso8601String(),
                ])->values(),
            ],
            'taxonomy' => CatalogTaxonomy::forBusiness($business),
            'suppliers' => Supplier::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'currency' => $business->currency,
        ]);
    }

    public function store(
        StoreProductRequest $request,
        TenantContext $tenant,
        ProductService $products,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $products->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Product created.');
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductService $products,
    ): RedirectResponse {
        $products->update($product, $request->validated(), $request->user());

        return back()->with('success', 'Product updated.');
    }

    public function destroy(
        Product $product,
        ProductService $products,
    ): RedirectResponse {
        $this->authorize('delete', $product);

        $products->delete($product, request()->user());

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(Product $product, string $currency): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'cost_price' => Money::fromMinor($product->cost_price, $currency),
            'cost_price_minor' => $product->cost_price,
            'cost_price_formatted' => Money::format($product->cost_price, $currency),
            'selling_price' => Money::fromMinor($product->selling_price, $currency),
            'selling_price_minor' => $product->selling_price,
            'selling_price_formatted' => Money::format($product->selling_price, $currency),
            'min_selling_price' => Money::fromMinor($product->min_selling_price, $currency),
            'min_selling_price_minor' => $product->min_selling_price,
            'min_selling_price_formatted' => Money::format($product->min_selling_price, $currency),
            'is_negotiable' => (bool) $product->is_negotiable,
            'reorder_level' => $product->reorder_level,
            'is_active' => $product->is_active,
            'suppliers_count' => $product->suppliers_count ?? $product->suppliers->count(),
        ];
    }
}
