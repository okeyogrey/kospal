<?php

namespace App\Http\Middleware;

use App\Contracts\FeatureFlagService;
use App\Contracts\LicensingService;
use App\Services\CashSessionService;
use App\Services\StaffShiftService;
use App\Support\Deployment;
use App\Support\Navigation;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Time\BusinessClock;
use App\Support\Time\OperatingHours;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $tenant = app(TenantContext::class);
        $user = $request->user();
        $role = $tenant->isPlatformSuperAdmin()
            ? 'platform_super_admin'
            : $tenant->role()?->value;

        $branches = [];
        if ($user && $tenant->hasBusiness()) {
            $branches = app(ResolvesTenant::class)
                ->allowedBranches($user, $tenant->membership(), $tenant->business())
                ->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->values()
                ->all();
        }

        $business = $tenant->business();
        $limits = null;
        $activeShift = null;
        $activeCashSession = null;
        $shiftRequired = false;
        $outsideHours = false;
        $licensing = app(LicensingService::class);
        $features = app(FeatureFlagService::class);

        if ($business) {
            $licensing->refreshStatus($business);
            $limits = $features->limitsPayload($business);

            $businessRole = $tenant->role();
            $branch = $tenant->branch();
            if ($user && $businessRole?->canClockShifts() && $branch) {
                $shiftRequired = true;
                $open = app(StaffShiftService::class)->openShiftFor($business, $user);
                if ($open !== null) {
                    $timezone = BusinessClock::resolve($business->timezone, $business->country);
                    $activeShift = [
                        'id' => $open->id,
                        'branch_id' => $open->branch_id,
                        'branch_name' => $open->branch?->name,
                        'clocked_in_at' => $open->clocked_in_at?->timezone($timezone)->toIso8601String(),
                        'on_active_branch' => $open->branch_id === $branch->id,
                    ];

                    $openCash = app(CashSessionService::class)->openSessionOnBranch($business, $user, $branch->id);
                    if ($openCash !== null) {
                        $activeCashSession = [
                            'id' => $openCash->id,
                            'opening_float_minor' => $openCash->opening_float,
                            'opened_at' => $openCash->opened_at?->timezone($timezone)->toIso8601String(),
                        ];
                    }
                }
                $outsideHours = OperatingHours::isOutsideHours($business, $branch);
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'deployment' => [
                'mode' => Deployment::mode(),
                'is_desktop' => Deployment::isDesktop(),
            ],
            'auth' => [
                'user' => $user,
                'role' => $role,
                'is_platform_super_admin' => (bool) $user?->is_platform_super_admin,
            ],
            'locale' => app()->getLocale(),
            'locales' => config('kospal.locales'),
            'currencies' => config('kospal.currencies'),
            'translations' => trans('kospal'),
            'navigation' => [
                'roleMap' => Navigation::roleMap(),
                'allowedKeys' => Navigation::keysForRole($role, $business),
            ],
            'workspace' => [
                'business' => $business ? [
                    'id' => $business->id,
                    'name' => $business->name,
                    'plan' => $licensing->plan($business)->value,
                    'subscription_status' => $licensing->status($business)->value,
                    'subscription_ends_at' => $business->fresh()->subscription_ends_at?->toDateString(),
                    'allows_write_access' => $licensing->allowsWriteAccess($business),
                    'country' => $business->country,
                    'currency' => $business->currency,
                    'cashiers_can_log_expenses' => (bool) $business->cashiers_can_log_expenses,
                ] : null,
                'branch' => $tenant->branch() ? [
                    'id' => $tenant->branch()->id,
                    'name' => $tenant->branch()->name,
                ] : null,
                'branches' => $branches,
                'limits' => $limits,
                'active_shift' => $activeShift,
                'active_cash_session' => $activeCashSession,
                'shift_required' => $shiftRequired,
                'outside_hours' => $outsideHours,
                'needs_onboarding' => $user !== null
                    && ! $user->is_platform_super_admin
                    && ! $tenant->hasBusiness(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
