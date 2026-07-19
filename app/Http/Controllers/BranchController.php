<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Requests\Business\UpdateOperatingHoursRequest;
use App\Models\Branch;
use App\Services\BranchService;
use App\Support\Plans\PlanLimitChecker;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(TenantContext $tenant, PlanLimitChecker $limits): Response
    {
        $this->authorize('viewAny', Branch::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $branches = Branch::query()
            ->forBusiness($business)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'city',
                'phone',
                'operating_mode',
                'opens_at',
                'closes_at',
                'is_active',
                'created_at',
            ]);

        return Inertia::render('branches/index', [
            'branches' => $branches->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'city' => $branch->city,
                'phone' => $branch->phone,
                'operating_mode' => $branch->operating_mode?->value,
                'opens_at' => $branch->opens_at ? substr((string) $branch->opens_at, 0, 5) : null,
                'closes_at' => $branch->closes_at ? substr((string) $branch->closes_at, 0, 5) : null,
                'is_active' => $branch->is_active,
                'created_at' => $branch->created_at?->toIso8601String(),
            ])->values(),
            'business_hours' => [
                'operating_mode' => $business->operating_mode?->value ?? 'always_open',
                'opens_at' => $business->opens_at ? substr((string) $business->opens_at, 0, 5) : null,
                'closes_at' => $business->closes_at ? substr((string) $business->closes_at, 0, 5) : null,
            ],
            'limits' => [
                'max_branches' => $limits->maxBranches($business),
                'active_branches' => $limits->activeBranchCount($business),
                'can_add_branch' => $limits->canAddBranch($business),
                'plan' => $business->plan->value,
            ],
        ]);
    }

    public function updateOperatingHours(
        UpdateOperatingHoursRequest $request,
        TenantContext $tenant,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $business->update($request->validated());

        return back()->with('success', 'Store operating hours updated.');
    }

    public function store(
        StoreBranchRequest $request,
        TenantContext $tenant,
        BranchService $branches,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $branches->create($business, $request->validated(), $request->user());

        return back()->with('success', 'Branch created.');
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
        BranchService $branches,
    ): RedirectResponse {
        $branches->update($branch, $request->validated(), $request->user());

        return back()->with('success', 'Branch updated.');
    }

    public function destroy(
        Branch $branch,
        BranchService $branches,
    ): RedirectResponse {
        $this->authorize('delete', $branch);

        $branches->delete($branch, request()->user());

        return back()->with('success', 'Branch deleted.');
    }
}
