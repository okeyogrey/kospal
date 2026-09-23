<?php

namespace App\Http\Middleware;

use App\Models\ReferralAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateReferralAccount
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Referral account token required.'], 401);
        }

        $account = ReferralAccount::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if ($account === null) {
            return response()->json(['message' => 'Invalid referral account token.'], 401);
        }

        $request->attributes->set('referral_account', $account);

        return $next($request);
    }
}
