<?php

namespace App\Http\Middleware;

use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Deployment::isDesktop(), 404);

        $user = $request->user();

        abort_unless($user?->isPlatformSuperAdmin(), 403);

        return $next($request);
    }
}
