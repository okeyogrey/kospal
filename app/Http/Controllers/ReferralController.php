<?php

namespace App\Http\Controllers;

use App\Http\Requests\Referrals\SendReferralInviteRequest;
use App\Services\ReferralService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(TenantContext $tenant, ReferralService $referrals): Response
    {
        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        return Inertia::render('referrals/index', $referrals->dashboardPayload(
            $business,
            request()->user(),
        ));
    }

    public function store(TenantContext $tenant, ReferralService $referrals): RedirectResponse
    {
        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $referrals->issueCode($business, request()->user());

        return back()->with('success', 'Invite link ready. Share it before it expires in 3 days.');
    }

    public function email(
        SendReferralInviteRequest $request,
        TenantContext $tenant,
        ReferralService $referrals,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);
        $this->authorize('manageSubscription', $business);

        $referrals->sendEmailInvite($business, $request->user(), $request->validated('email'));

        return back()->with('success', 'Invite email sent.');
    }
}
