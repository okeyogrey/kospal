<?php

use App\Http\Middleware\AuthenticateReferralAccount;
use App\Http\Middleware\AuthenticateSyncDevice;
use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\EnsureBusinessMembership;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use App\Http\Middleware\EnsureStaffShiftActive;
use App\Http\Middleware\EnsureSubscriptionAllowsWrites;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RefreshSharedShop;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            CaptureReferralCode::class,
            SetLocale::class,
            HandleAppearance::class,
            ResolveTenantContext::class,
            RefreshSharedShop::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant context must be available before route model bindings
        // that scope records to the current business.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenantContext::class,
        );

        $middleware->alias([
            'business' => EnsureBusinessMembership::class,
            'platform' => EnsurePlatformSuperAdmin::class,
            'subscription.write' => EnsureSubscriptionAllowsWrites::class,
            'shift.active' => EnsureStaffShiftActive::class,
            'referral.account' => AuthenticateReferralAccount::class,
            'sync.device' => AuthenticateSyncDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
