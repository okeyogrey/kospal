<?php

namespace App\Http\Middleware;

use App\Contracts\LicensingService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAllowsWrites
{
    public function __construct(
        protected TenantContext $tenant,
        protected LicensingService $licensing,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isPlatformSuperAdmin()) {
            return $next($request);
        }

        $business = $this->tenant->business();

        if ($business === null) {
            return $next($request);
        }

        $this->licensing->refreshStatus($business);

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $requiresWrite = ! $request->isMethodSafe() && ! $request->isMethod('HEAD')
            || $request->routeIs('reports.export');

        if (! $requiresWrite) {
            return $next($request);
        }

        if ($this->licensing->allowsWriteAccess($business)) {
            return $next($request);
        }

        $message = $this->licensing->restrictionMessage($business);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }

    protected function isExempt(Request $request): bool
    {
        return $request->routeIs([
            'subscription.*',
            'license.*',
            'locale.update',
            'workspace.*',
            'logout',
            'profile.*',
            'user-password.*',
            'password.*',
            'appearance.*',
            'two-factor.*',
            'verification.*',
            'login',
            'logout',
        ]);
    }
}
