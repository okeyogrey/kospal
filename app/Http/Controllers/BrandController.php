<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brands\StoreBrandRequest;
use App\Http\Requests\Brands\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\BrandService;
use App\Support\Catalog\CatalogTaxonomy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Brand::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $taxonomy = CatalogTaxonomy::forBusiness($business);

        $categories = Category::query()
            ->forBusiness($business)
            ->get(['id', 'name', 'parent_id']);
        $byId = $categories->keyBy('id');

        $brands = Brand::query()
            ->forBusiness($business)
            ->withCount('products')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'category_id', 'is_active']);

        return Inertia::render('brands/index', [
            'brands' => $brands->map(function (Brand $brand) use ($byId): array {
                $category = $byId->get($brand->category_id);

                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'description' => $brand->description,
                    'category_id' => $brand->category_id,
                    'category_path' => $category
                        ? CatalogTaxonomy::pathForCategory($category, $byId)
                        : null,
                    'is_active' => $brand->is_active,
                    'products_count' => $brand->products_count,
                ];
            })->values(),
            'taxonomy' => $taxonomy,
        ]);
    }

    public function store(
        StoreBrandRequest $request,
        TenantContext $tenant,
        BrandService $brands,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $brands->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Brand created.');
    }

    public function update(
        UpdateBrandRequest $request,
        Brand $brand,
        BrandService $brands,
    ): RedirectResponse {
        $brands->update($brand, $request->validated(), $request->user());

        return back()->with('success', 'Brand updated.');
    }

    public function destroy(
        Brand $brand,
        BrandService $brands,
    ): RedirectResponse {
        $this->authorize('delete', $brand);

        $brands->delete($brand, request()->user());

        return back()->with('success', 'Brand deleted.');
    }
}
