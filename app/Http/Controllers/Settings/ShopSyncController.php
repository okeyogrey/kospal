<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Models\SyncConflict;
use App\Models\SyncLink;
use App\Models\SyncOutbox;
use App\Services\Sync\ShopSyncService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopSyncController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(TenantContext $tenant): Response
    {
        $business = $this->desktopBusiness($tenant);
        $link = SyncLink::query()->where('business_id', $business->id)->first();

        $conflicts = SyncConflict::query()
            ->where('business_id', $business->id)
            ->whereNull('resolved_at')
            ->with('sale:id,sale_number')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (SyncConflict $conflict): array => [
                'id' => $conflict->id,
                'sale_id' => $conflict->sale_id,
                'sale_number' => $conflict->sale?->sale_number,
                'message' => $conflict->message,
                'created_at' => $conflict->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('settings/shops', [
            'serverUrl' => $link->server_url ?? (string) config('kospal.sync.server_url', ''),
            'linked' => $link !== null,
            'joinCode' => $link?->join_code,
            'lastSyncedAt' => $link?->lastSyncedIso(),
            'lastError' => $link?->last_error,
            'pending' => $link === null
                ? 0
                : SyncOutbox::query()
                    ->where('business_id', $business->id)
                    ->whereNull('pushed_at')
                    ->count(),
            'conflicts' => $conflicts,
        ]);
    }

    public function status(TenantContext $tenant): JsonResponse
    {
        $business = $tenant->business();
        abort_unless($business !== null, 403);

        $link = SyncLink::query()->where('business_id', $business->id)->first();

        $conflicts = SyncConflict::query()
            ->where('business_id', $business->id)
            ->whereNull('resolved_at')
            ->with('sale:id,sale_number')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (SyncConflict $conflict): array => [
                'sale_id' => $conflict->sale_id,
                'sale_number' => $conflict->sale?->sale_number,
                'message' => $conflict->message,
            ])
            ->all();

        return response()->json([
            'linked' => $link !== null,
            'last_synced_at' => $link?->lastSyncedIso(),
            'last_error' => $link?->last_error,
            'conflicts' => $conflicts,
        ]);
    }

    public function link(Request $request, TenantContext $tenant, ShopSyncService $sync): RedirectResponse
    {
        $business = $this->desktopBusiness($tenant);

        $data = $request->validate([
            'server_url' => ['required', 'url', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $sync->link(
            $business,
            $data['server_url'],
            $data['device_name'] ?? php_uname('n'),
        );

        return back()->with('success', 'This shop is now linked. Other computers can join with the code below.');
    }

    public function join(Request $request, TenantContext $tenant, ShopSyncService $sync): RedirectResponse
    {
        $business = $this->desktopBusiness($tenant);
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'server_url' => ['required', 'url', 'max:255'],
            'join_code' => ['required', 'string', 'max:32'],
        ]);

        $sync->join($business, $user, $data['server_url'], $data['join_code']);

        return back()->with('success', 'This computer joined the shop. Sign in with the original staff account to use the same password.');
    }

    public function syncNow(TenantContext $tenant, ShopSyncService $sync): RedirectResponse
    {
        $business = $this->desktopBusiness($tenant);
        $link = SyncLink::query()->where('business_id', $business->id)->first();

        if ($link === null) {
            return back()->with('error', 'Link this shop before syncing.');
        }

        $sync->run($link);

        return back()->with('success', 'Shops synced.');
    }

    public function regenerate(TenantContext $tenant, ShopSyncService $sync): RedirectResponse
    {
        $business = $this->desktopBusiness($tenant);
        $link = SyncLink::query()->where('business_id', $business->id)->firstOrFail();
        $sync->regenerate($link);

        return back()->with('success', 'A new join code is ready. The previous code no longer works.');
    }
}
