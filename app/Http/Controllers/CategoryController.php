<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Category::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $categories = Category::query()
            ->forBusiness($business)
            ->withCount('products')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'created_at'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(
        StoreCategoryRequest $request,
        TenantContext $tenant,
        CategoryService $categories,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $categories->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Category created.');
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        CategoryService $categories,
    ): RedirectResponse {
        $categories->update($category, $request->validated(), $request->user());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(
        Category $category,
        CategoryService $categories,
    ): RedirectResponse {
        $this->authorize('delete', $category);

        $categories->delete($category, request()->user());

        return back()->with('success', 'Category deleted.');
    }
}
