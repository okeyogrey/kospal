<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\VoidReferralRequest;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(Request $request, ReferralService $referrals): Response
    {
        $this->authorize('viewAny', Referral::class);

        return Inertia::render(
            'platform/referrals/index',
            $referrals->platformIndexPayload($request->string('status')->toString()),
        );
    }

    public function void(
        VoidReferralRequest $request,
        Referral $referral,
        ReferralService $referrals,
    ): RedirectResponse {
        $this->authorize('void', $referral);

        $referrals->voidReferral($referral, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Referral voided. Unused credits were removed.');
    }
}
