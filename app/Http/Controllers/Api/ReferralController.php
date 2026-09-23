<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferralAccount;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function register(Request $request, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate([
            'public_uuid' => ['required', 'uuid'],
            'owner_email' => ['required', 'email', 'max:255'],
            'business_name' => ['required', 'string', 'max:120'],
            'machine_id' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json($referrals->registerRemoteAccount($data), 201);
    }

    public function preview(string $code, ReferralService $referrals): JsonResponse
    {
        return response()->json($referrals->preview($code));
    }

    public function redeem(Request $request, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'public_uuid' => ['required', 'uuid'],
            'owner_email' => ['required', 'email', 'max:255'],
            'business_name' => ['required', 'string', 'max:120'],
            'machine_id' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json($referrals->redeemRemote($data), 201);
    }

    public function issue(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json($referrals->issueCodeForAccount($this->account($request)), 201);
    }

    public function dashboard(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json($referrals->dashboardForAccount($this->account($request)));
    }

    public function applyPayment(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json($referrals->applyPaymentForAccount($this->account($request)));
    }

    public function heartbeat(Request $request, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $referrals->heartbeat($this->account($request), (bool) $data['is_active']);

        return response()->json(['ok' => true]);
    }

    public function email(Request $request, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $referrals->sendEmailForAccount($this->account($request), $data['email'], $this->account($request)->owner);

        return response()->json(['ok' => true]);
    }

    protected function account(Request $request): ReferralAccount
    {
        $account = $request->attributes->get('referral_account');
        abort_unless($account instanceof ReferralAccount, 401);

        return $account;
    }
}
