<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Http\Requests\Staff\StoreInvitationRequest;
use App\Http\Requests\Staff\UpdateMembershipRequest;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Invitation;
use App\Services\StaffInvitationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(TenantContext $tenant, FeatureFlagService $limits): Response
    {
        $this->authorize('viewAny', BusinessMembership::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $memberships = BusinessMembership::query()
            ->forBusiness($business)
            ->with(['user:id,name,email'])
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get()
            ->map(function (BusinessMembership $membership) {
                $branchIds = $membership->user->branches()
                    ->where('branches.business_id', $membership->business_id)
                    ->pluck('branches.id');

                return [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'negotiation_floor_percent' => (int) $membership->negotiation_floor_percent,
                    'is_active' => $membership->is_active,
                    'joined_at' => $membership->joined_at,
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                    ],
                    'branch_ids' => $branchIds,
                ];
            });

        $invitations = Invitation::query()
            ->forBusiness($business)
            ->where('status', InvitationStatus::Pending)
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'status', 'expires_at', 'branch_ids', 'created_at']);

        $branches = Branch::query()
            ->forBusiness($business)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('staff/index', [
            'memberships' => $memberships,
            'invitations' => $invitations,
            'branches' => $branches,
            'assignableRoles' => $tenant->role() === BusinessRole::Manager
                ? array_map(fn (BusinessRole $role) => $role->value, BusinessRole::assignableByManager())
                : array_values(array_filter(
                    BusinessRole::values(),
                    fn (string $role) => $role !== BusinessRole::Owner->value,
                )),
            'limits' => [
                'max_staff' => $limits->maxStaff($business),
                'staff_seats' => $limits->staffSeatCount($business),
                'can_add_staff' => $limits->canAddStaff($business),
                'plan' => $business->plan->value,
            ],
            'settings' => [
                'cashiers_can_log_expenses' => (bool) $business->cashiers_can_log_expenses,
                'can_edit' => $tenant->role() === BusinessRole::Owner,
            ],
        ]);
    }

    public function updateSettings(Request $request, TenantContext $tenant): RedirectResponse
    {
        $business = $tenant->business();
        abort_unless($business && $tenant->role() === BusinessRole::Owner, 403);

        $validated = $request->validate([
            'cashiers_can_log_expenses' => ['required', 'boolean'],
        ]);

        $business->update([
            'cashiers_can_log_expenses' => $validated['cashiers_can_log_expenses'],
        ]);

        return back()->with('success', 'Staff expense settings updated.');
    }

    public function invite(
        StoreInvitationRequest $request,
        TenantContext $tenant,
        StaffInvitationService $staff,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $data = $request->validated();

        $staff->invite(
            $business,
            $request->user(),
            $data['email'],
            BusinessRole::from($data['role']),
            $data['branch_ids'] ?? [],
        );

        return back()->with('success', 'Invitation sent.');
    }

    public function updateMembership(
        UpdateMembershipRequest $request,
        BusinessMembership $membership,
        StaffInvitationService $staff,
    ): RedirectResponse {
        $data = $request->validated();

        $staff->updateMembershipRole(
            $membership,
            BusinessRole::from($data['role']),
            $data['branch_ids'] ?? [],
            $request->user(),
            isset($data['negotiation_floor_percent'])
                ? (int) $data['negotiation_floor_percent']
                : null,
        );

        return back()->with('success', 'Staff member updated.');
    }

    public function deactivateMembership(
        BusinessMembership $membership,
        StaffInvitationService $staff,
    ): RedirectResponse {
        $this->authorize('deactivate', $membership);

        $staff->deactivateMembership($membership, request()->user());

        return back()->with('success', 'Staff member deactivated.');
    }

    public function revokeInvitation(
        Invitation $invitation,
        StaffInvitationService $staff,
    ): RedirectResponse {
        $this->authorize('revoke', $invitation);

        $staff->revoke($invitation, request()->user());

        return back()->with('success', 'Invitation revoked.');
    }
}
