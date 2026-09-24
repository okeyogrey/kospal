<?php

namespace App\Http\Middleware;

use App\Models\SyncLink;
use App\Models\SyncOutbox;
use App\Services\Sync\ShopSyncService;
use App\Support\Deployment;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pulls other computers' changes before a page renders, then sends this
 * computer's new sales and stock updates after the response.
 */
class RefreshSharedShop
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->sync(pendingOnly: false);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->sync(pendingOnly: true);
    }

    private function sync(bool $pendingOnly): void
    {
        if (app()->runningUnitTests() || ! Deployment::isDesktop()) {
            return;
        }

        if (! Schema::hasTable('sync_links') || ! Schema::hasTable('sync_outbox')) {
            return;
        }

        $business = app(TenantContext::class)->business();

        if ($business === null) {
            return;
        }

        $link = SyncLink::query()->where('business_id', $business->id)->first();

        if ($link === null) {
            return;
        }

        if ($pendingOnly) {
            $pending = SyncOutbox::query()
                ->where('business_id', $business->id)
                ->whereNull('pushed_at')
                ->exists();

            if (! $pending) {
                return;
            }
        } elseif (! Cache::add('shop-sync-due:'.$business->id, 1, now()->addSeconds(5))) {
            return;
        }

        $lockKey = 'shop-sync-lock:'.$business->id;

        if (! Cache::add($lockKey, 1, now()->addSeconds(20))) {
            return;
        }

        try {
            app(ShopSyncService::class)->run($link);
        } catch (\Throwable) {
            // The link stores the error. The page still opens from this computer's copy.
        } finally {
            Cache::forget($lockKey);
        }
    }
}
