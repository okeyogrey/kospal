<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    public function switchBranch(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
    ): RedirectResponse {
        $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $business = $tenant->business();
        $membership = $tenant->membership();
        abort_unless($business && $membership, 403);

        $branch = Branch::query()
            ->forBusiness($business)
            ->whereKey($request->integer('branch_id'))
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch is invalid.',
            ]);
        }

        $allowed = $resolver->allowedBranches($request->user(), $membership, $business);

        if (! $allowed->contains('id', $branch->id)) {
            abort(403);
        }

        $request->user()->forceFill([
            'current_branch_id' => $branch->id,
        ])->save();

        $resolver->resolve($request->user()->fresh());

        return back();
    }

    public function switchBusiness(
        Request $request,
        ResolvesTenant $resolver,
    ): RedirectResponse {
        $request->validate([
            'business_id' => ['required', 'integer'],
        ]);

        $membership = BusinessMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('business_id', $request->integer('business_id'))
            ->where('is_active', true)
            ->first();

        abort_unless($membership, 403);

        $request->user()->forceFill([
            'current_business_id' => $membership->business_id,
            'current_branch_id' => null,
        ])->save();

        $resolver->resolve($request->user()->fresh());

        return back();
    }
}
