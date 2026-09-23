<?php

namespace App\Http\Middleware;

use App\Services\Sync\SyncHub;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSyncDevice
{
    public function __construct(
        protected SyncHub $hub,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Shop sync token required.'], 401);
        }

        $device = $this->hub->deviceFromToken($token);

        if ($device === null) {
            return response()->json(['message' => 'Invalid shop sync token.'], 401);
        }

        $request->attributes->set('sync_device', $device);

        return $next($request);
    }
}
