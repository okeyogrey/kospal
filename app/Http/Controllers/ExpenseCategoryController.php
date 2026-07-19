<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategories\StoreExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategories\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $search = trim((string) $request->query('search', ''));

        $categories = ExpenseCategory::query()
            ->forBusiness($business)
            ->when($search !== '', fn ($query) => $query->search($search))
            ->withCount('expenses')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'expenses_count' => $category->expenses_count,
            ]);

        return Inertia::render('expenses/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        StoreExpenseCategoryRequest $request,
        TenantContext $tenant,
        ExpenseCategoryService $categories,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $categories->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Expense category created.');
    }

    public function update(
        UpdateExpenseCategoryRequest $request,
        ExpenseCategory $expenseCategory,
        ExpenseCategoryService $categories,
    ): RedirectResponse {
        $categories->update($expenseCategory, $request->validated(), $request->user());

        return back()->with('success', 'Expense category updated.');
    }

    public function destroy(
        ExpenseCategory $expenseCategory,
        ExpenseCategoryService $categories,
    ): RedirectResponse {
        $this->authorize('delete', $expenseCategory);

        $categories->delete($expenseCategory, request()->user());

        return back()->with('success', 'Expense category deleted.');
    }
}
