<?php

namespace App\Http\Middleware;

use App\Support\Deployment;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessMembership
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // Web mode: platform admins have no tenant membership; keep them on platform tools.
        if ($user->isPlatformSuperAdmin() && Deployment::isWeb()) {
            return redirect()->route('platform.subscription-requests.index');
        }

        if (! $this->tenant->hasBusiness()) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
