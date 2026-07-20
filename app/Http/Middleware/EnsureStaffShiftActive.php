<?php

namespace App\Http\Middleware;

use App\Models\Sale;
use App\Services\CashSessionService;
use App\Services\StaffShiftService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffShiftActive
{
    public function __construct(
        protected TenantContext $tenant,
        protected StaffShiftService $shifts,
        protected CashSessionService $cashSessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $role = $this->tenant->role();
        $business = $this->tenant->business();
        $branch = $this->tenant->branch();
        $user = $this->tenant->user();

        if ($role === null || $business === null || $branch === null || $user === null) {
            return $next($request);
        }

        if (! $role->canClockShifts()) {
            return $next($request);
        }

        if (! $this->userIsAllowedForRoute($request, $user)) {
            return $next($request);
        }

        if ($this->shifts->hasOpenShiftOnBranch($business, $user, $branch)) {
            if ($request->routeIs('sales.store')
                && ! $this->cashSessions->hasOpenSessionOnBranch($business, $user, $branch->id)) {
                $message = 'Open the cash drawer before making sales.';

                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    return response()->json(['message' => $message], 403);
                }

                return redirect()
                    ->back()
                    ->with('error', $message)
                    ->withErrors(['cash_session' => $message]);
            }

            return $next($request);
        }

        $message = 'Clock in on this branch before continuing.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()
            ->back()
            ->with('error', $message)
            ->withErrors(['shift' => $message]);
    }

    protected function userIsAllowedForRoute(Request $request, $user): bool
    {
        if ($request->routeIs('sales.store')) {
            return $user->can('create', Sale::class);
        }

        if ($request->routeIs('sales.void')) {
            $sale = $request->route('sale');

            return $sale instanceof Sale && $user->can('void', $sale);
        }

        if ($request->routeIs(
            'inventory.receive-stock.store',
            'inventory.adjustments.store',
            'goods-received.post',
            'stock-counts.complete',
        )) {
            $role = $this->tenant->role();

            return $role !== null && $role->canManageCatalog();
        }

        return true;
    }
}
