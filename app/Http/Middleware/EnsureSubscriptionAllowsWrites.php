<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAllowsWrites
{
    public function __construct(
        protected TenantContext $tenant,
        protected SubscriptionService $subscriptions,
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

        $this->subscriptions->expireIfPastDue($business);

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $requiresWrite = ! $request->isMethodSafe() && ! $request->isMethod('HEAD')
            || $request->routeIs('reports.export');

        if (! $requiresWrite) {
            return $next($request);
        }

        if ($business->allowsWriteAccess()) {
            return $next($request);
        }

        $message = $this->restrictionMessage($business->subscription_status->value);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }

    protected function isExempt(Request $request): bool
    {
        return $request->routeIs([
            'subscription.*',
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

    protected function restrictionMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'Your subscription is pending approval. Submit a payment transaction code on the Subscription page, then wait for platform review.',
            'expired' => 'Your subscription has expired. Submit a new payment on the Subscription page to restore write access.',
            'suspended' => 'Your subscription is suspended. Contact support or submit a new payment request from the Subscription page.',
            default => 'Your subscription does not allow this action. Visit the Subscription page for next steps.',
        };
    }
}
