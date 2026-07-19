<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AttachmentService;
use App\Services\ExpenseService;
use App\Support\Money\Money;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        AttachmentService $attachments,
    ): Response {
        $this->authorize('viewAny', Expense::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $search = trim((string) $request->query('search', ''));
        $branchId = $request->integer('branch_id') ?: null;
        $categoryId = $request->integer('expense_category_id') ?: null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $expenses = Expense::query()
            ->forBusiness($business)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when(is_string($dateFrom) && $dateFrom !== '', fn ($q) => $q->whereDate('expense_date', '>=', $dateFrom))
            ->when(is_string($dateTo) && $dateTo !== '', fn ($q) => $q->whereDate('expense_date', '<=', $dateTo))
            ->when($search !== '', fn ($q) => $q->search($search))
            ->with([
                'branch:id,name',
                'category:id,name',
                'creator:id,name',
                'receipt',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Expense $expense) => $this->listPayload($expense, $attachments));

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'categories' => ExpenseCategory::query()
                ->forBusiness($business)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn (ExpenseCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_active' => $c->is_active,
                ]),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'expense_category_id' => $categoryId,
                'date_from' => is_string($dateFrom) ? $dateFrom : null,
                'date_to' => is_string($dateTo) ? $dateTo : null,
            ],
            'permissions' => [
                'create' => $user->can('create', Expense::class),
                'manage_categories' => $user->can('viewAny', ExpenseCategory::class),
            ],
            'currency' => $business->currency,
        ]);
    }

    public function create(TenantContext $tenant, ResolvesTenant $resolver): Response
    {
        $this->authorize('create', Expense::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);

        return Inertia::render('expenses/create', [
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'categories' => ExpenseCategory::query()
                ->forBusiness($business)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExpenseCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
            'defaultBranchId' => $tenant->branch()?->id,
            'currency' => $business->currency,
            'attachmentLimits' => [
                'max_kilobytes' => (int) config('kospal.attachments.max_kilobytes', 5120),
                'allowed_mimes' => config('kospal.attachments.allowed_mimes', []),
            ],
        ]);
    }

    public function store(
        StoreExpenseRequest $request,
        TenantContext $tenant,
        ExpenseService $expenses,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $data = $request->validated();
        unset($data['receipt']);

        $expense = $expenses->create(
            business: $business,
            data: $data,
            actor: $request->user(),
            receipt: $request->file('receipt'),
        );

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', 'Expense recorded.');
    }

    public function show(
        Expense $expense,
        TenantContext $tenant,
        AttachmentService $attachments,
    ): Response {
        $this->authorize('view', $expense);

        $business = $tenant->business();
        abort_unless($business, 403);

        $expense->load([
            'branch:id,name',
            'category:id,name,is_active',
            'creator:id,name',
            'receipt',
        ]);

        $activity = AuditLog::query()
            ->forBusiness($business)
            ->where('auditable_type', $expense->getMorphClass())
            ->where('auditable_id', $expense->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_name' => $log->user?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('expenses/show', [
            'expense' => $this->detailPayload($expense, $attachments),
            'activity' => $activity,
            'permissions' => [
                'update' => $tenant->user()?->can('update', $expense) ?? false,
                'delete' => $tenant->user()?->can('delete', $expense) ?? false,
            ],
            'currency' => $business->currency,
        ]);
    }

    public function edit(
        Expense $expense,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        AttachmentService $attachments,
    ): Response {
        $this->authorize('update', $expense);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $expense->load(['receipt', 'category:id,name,is_active']);
        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);

        $categories = ExpenseCategory::query()
            ->forBusiness($business)
            ->where(function ($query) use ($expense): void {
                $query->where('is_active', true)
                    ->orWhereKey($expense->expense_category_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return Inertia::render('expenses/edit', [
            'expense' => $this->detailPayload($expense, $attachments),
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'categories' => $categories->map(fn (ExpenseCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'is_active' => $c->is_active,
            ]),
            'currency' => $business->currency,
            'attachmentLimits' => [
                'max_kilobytes' => (int) config('kospal.attachments.max_kilobytes', 5120),
                'allowed_mimes' => config('kospal.attachments.allowed_mimes', []),
            ],
        ]);
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        ExpenseService $expenses,
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['receipt']);

        $expenses->update(
            expense: $expense,
            data: $data,
            actor: $request->user(),
            receipt: $request->file('receipt'),
        );

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', 'Expense updated.');
    }

    public function destroy(
        Expense $expense,
        ExpenseService $expenses,
    ): RedirectResponse {
        $this->authorize('delete', $expense);

        $expenses->delete($expense, request()->user());

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Expense $expense, AttachmentService $attachments): array
    {
        return [
            'id' => $expense->id,
            'branch_id' => $expense->branch_id,
            'branch_name' => $expense->branch?->name,
            'expense_category_id' => $expense->expense_category_id,
            'category_name' => $expense->category?->name,
            'expense_date' => $expense->expense_date?->toDateString(),
            'currency' => $expense->currency,
            'amount' => $expense->amount,
            'amount_formatted' => Money::format($expense->amount, $expense->currency),
            'payee' => $expense->payee,
            'description' => $expense->description,
            'created_by_name' => $expense->creator?->name,
            'has_receipt' => $expense->receipt !== null,
            'receipt' => $expense->receipt ? $attachments->payload($expense->receipt) : null,
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPayload(Expense $expense, AttachmentService $attachments): array
    {
        return [
            ...$this->listPayload($expense, $attachments),
            'amount_input' => Money::fromMinor($expense->amount, $expense->currency),
            'category_is_active' => $expense->category?->is_active,
        ];
    }
}
