<?php

namespace App\Services\Sync;

use App\Enums\Plan;
use App\Enums\StockMovementType;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\SyncConflict;
use App\Models\SyncDeferredOperation;
use App\Models\SyncIdentity;
use App\Models\SyncLink;
use App\Models\SyncOutbox;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopSyncService
{
    public function __construct(
        protected SyncGateway $gateway,
        protected SyncRecorder $recorder,
        protected SyncApplier $applier,
    ) {}

    public function link(Business $business, string $serverUrl, string $deviceName): SyncLink
    {
        if (SyncLink::query()->where('business_id', $business->id)->exists()) {
            throw ValidationException::withMessages([
                'server_url' => 'This shop is already connected.',
            ]);
        }

        $owner = User::query()->find($business->owner_user_id);
        $deviceUuid = (string) Str::uuid();

        $result = $this->gateway->register($serverUrl, [
            'business_public_uuid' => (string) $business->public_uuid,
            'business_name' => $business->name,
            'owner_email' => $owner instanceof User ? $owner->email : 'owner@localhost',
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName,
        ]);

        $link = SyncLink::query()->create([
            'business_id' => $business->id,
            'server_url' => $this->serverUrl($serverUrl),
            'token' => $result['token'],
            'device_uuid' => $deviceUuid,
            'join_code' => $result['join_code'],
        ]);

        $this->recorder->snapshot($business);
        $this->pushAll($link);

        return $link->fresh() ?? $link;
    }

    public function join(Business $business, User $user, string $serverUrl, string $joinCode): SyncLink
    {
        if (SyncLink::query()->where('business_id', $business->id)->exists()) {
            throw ValidationException::withMessages([
                'join_code' => 'This computer is already connected to a shop.',
            ]);
        }

        if ($this->hasLocalHistory($business)) {
            throw ValidationException::withMessages([
                'join_code' => 'This computer already has products or sales. Join from a new installation before you start selling.',
            ]);
        }

        $deviceUuid = (string) Str::uuid();

        $result = $this->gateway->join($serverUrl, [
            'join_code' => $joinCode,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Joined computer',
        ]);

        SyncContext::silence(function () use ($business, $user, $result): void {
            Branch::query()->where('business_id', $business->id)->delete();

            DB::table('users')
                ->where('current_business_id', $business->id)
                ->update(['current_branch_id' => null]);

            $remoteUuid = $result['business_public_uuid'];

            try {
                $business->forceFill([
                    'public_uuid' => $remoteUuid,
                    'name' => $result['business_name'],
                ])->save();
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'public_uuid')) {
                    throw $exception;
                }

                SyncIdentity::query()->updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'remote_uuid' => $remoteUuid,
                    ],
                    [
                        'local_uuid' => (string) $business->getOriginal('public_uuid'),
                    ],
                );

                $business->forceFill([
                    'public_uuid' => $business->getOriginal('public_uuid'),
                    'name' => $result['business_name'],
                ])->save();
            }

            $user->forceFill(['current_branch_id' => null])->save();
        });

        $link = SyncLink::query()->create([
            'business_id' => $business->id,
            'server_url' => $this->serverUrl($serverUrl),
            'token' => $result['token'],
            'device_uuid' => $deviceUuid,
            'join_code' => $result['join_code'],
        ]);

        $this->pullAll($link->fresh() ?? $link);

        $branch = Branch::query()->where('business_id', $business->id)->orderBy('id')->first();

        if ($branch !== null) {
            $user->forceFill([
                'current_business_id' => $business->id,
                'current_branch_id' => $branch->id,
            ])->save();
        }

        return $link->fresh() ?? $link;
    }

    public function regenerate(SyncLink $link): SyncLink
    {
        $result = $this->gateway->regenerate($link->server_url, (string) $link->token);
        $link->forceFill(['join_code' => $result['join_code']])->save();

        return $link;
    }

    public function run(SyncLink $link): void
    {
        try {
            $this->pushAll($link);
            $this->pullAll($link);
            $this->applyOfficeStatus($link);
            $link->forceFill([
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $link->forceFill([
                'last_error' => Str::limit($exception->getMessage(), 500),
            ])->save();

            throw $exception;
        }
    }

    private function pushAll(SyncLink $link): void
    {
        for ($round = 0; $round < 6; $round++) {
            $pending = SyncOutbox::query()
                ->where('business_id', $link->business_id)
                ->whereNull('pushed_at')
                ->orderBy('id')
                ->limit(50)
                ->get();

            if ($pending->isEmpty()) {
                return;
            }

            $operations = array_values($pending->map(function (SyncOutbox $row): array {
                $occurredAt = $row->getAttribute('occurred_at');
                $payload = $row->getAttribute('payload');

                return [
                    'uuid' => (string) $row->uuid,
                    'entity_type' => (string) $row->entity_type,
                    'entity_uuid' => (string) $row->entity_uuid,
                    'op' => (string) $row->op,
                    'payload' => is_array($payload) ? $payload : [],
                    'occurred_at' => $occurredAt instanceof \DateTimeInterface
                        ? $occurredAt->format('Y-m-d H:i:s')
                        : null,
                ];
            })->all());

            $result = $this->gateway->push($link->server_url, (string) $link->token, $operations);

            $done = array_values(array_unique(array_merge(
                $result['accepted'],
                $result['ignored'],
                array_map(static fn (array $conflict): string => $conflict['uuid'], $result['conflicts']),
            )));

            if ($done !== []) {
                SyncOutbox::query()->whereIn('uuid', $done)->update([
                    'pushed_at' => now(),
                    'last_error' => null,
                ]);
            }

            $this->resolveConflicts($link, $result['conflicts']);

            if ($result['conflicts'] === [] && $pending->count() < 50) {
                return;
            }
        }
    }

    private function pullAll(SyncLink $link): void
    {
        $this->retryDeferred($link);

        for ($round = 0; $round < 100; $round++) {
            $operations = $this->gateway->pull($link->server_url, (string) $link->token, (int) $link->last_pulled_id);

            if ($operations === []) {
                break;
            }

            $business = $link->business;

            if ($business === null) {
                return;
            }

            $deferred = [];

            foreach ($operations as $operation) {
                if ($this->applier->apply($business, $link, $operation) === 'deferred') {
                    $deferred[] = $operation;
                }
            }

            foreach ($deferred as $operation) {
                if ($this->applier->apply($business, $link, $operation) !== 'deferred') {
                    continue;
                }

                $serverId = (int) $operation['id'];

                if ($serverId < 1) {
                    continue;
                }

                SyncDeferredOperation::query()->updateOrCreate(
                    [
                        'sync_link_id' => $link->id,
                        'server_operation_id' => $serverId,
                    ],
                    [
                        'body' => $operation,
                    ],
                );
            }

            $lastId = 0;

            foreach ($operations as $operation) {
                $lastId = max($lastId, (int) $operation['id']);
            }

            if ($lastId > (int) $link->last_pulled_id) {
                $link->forceFill(['last_pulled_id' => $lastId])->save();
            }

            $this->retryDeferred($link);

            if (count($operations) < 100) {
                break;
            }
        }
    }

    private function retryDeferred(SyncLink $link): void
    {
        $business = $link->business;

        if ($business === null) {
            return;
        }

        $rows = SyncDeferredOperation::query()
            ->where('sync_link_id', $link->id)
            ->orderBy('server_operation_id')
            ->get();

        foreach ($rows as $row) {
            $body = $row->getAttribute('body');

            if (! is_array($body)) {
                $row->delete();

                continue;
            }

            $uuid = $body['uuid'] ?? null;
            $entityType = $body['entity_type'] ?? null;
            $entityUuid = $body['entity_uuid'] ?? null;
            $op = $body['op'] ?? null;
            $payload = $body['payload'] ?? [];

            if (! is_string($uuid) || ! is_string($entityType) || ! is_string($entityUuid) || ! is_string($op)) {
                $row->delete();

                continue;
            }

            $applied = $this->applier->apply($business, $link, [
                'id' => is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0,
                'uuid' => $uuid,
                'device_uuid' => is_string($body['device_uuid'] ?? null) ? $body['device_uuid'] : '',
                'entity_type' => $entityType,
                'entity_uuid' => $entityUuid,
                'op' => $op,
                'payload' => is_array($payload) ? $payload : [],
            ]);

            if ($applied !== 'deferred') {
                $row->delete();
            }
        }
    }

    /**
     * @param  list<array{uuid: string, entity_uuid: string, entity_type: string, reason: string, message: string}>  $conflicts
     */
    private function resolveConflicts(SyncLink $link, array $conflicts): void
    {
        $business = $link->business;

        if ($business === null) {
            return;
        }

        foreach ($conflicts as $conflict) {
            if ($conflict['entity_type'] !== 'stock_movements') {
                continue;
            }

            $movement = StockMovement::query()
                ->where('business_id', $business->id)
                ->where('public_uuid', $conflict['entity_uuid'])
                ->first();

            if ($movement === null || (bool) $movement->sync_local_only) {
                continue;
            }

            $sale = $movement->reference instanceof Sale ? $movement->reference : null;

            if ($sale !== null && $sale->sync_conflict_at !== null) {
                continue;
            }

            SyncContext::silence(function () use ($movement): void {
                $balance = InventoryBalance::query()
                    ->where('business_id', $movement->business_id)
                    ->where('branch_id', $movement->branch_id)
                    ->where('product_id', $movement->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    return;
                }

                $before = (int) $balance->quantity;
                $after = $before - (int) $movement->quantity_delta;

                if ($after < 0) {
                    $after = 0;
                }

                $balance->update(['quantity' => $after]);

                $reversal = new StockMovement;
                $reversal->forceFill([
                    'business_id' => $movement->business_id,
                    'branch_id' => $movement->branch_id,
                    'product_id' => $movement->product_id,
                    'user_id' => $movement->user_id,
                    'type' => StockMovementType::SyncRejection,
                    'quantity_delta' => -1 * (int) $movement->quantity_delta,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'note' => 'Rejected because another computer already used this stock.',
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'sync_local_only' => true,
                    'created_at' => now(),
                ]);
                $reversal->save();
            });

            if ($sale !== null) {
                $sale->forceFill([
                    'sync_conflict_reason' => $conflict['message'],
                    'sync_conflict_at' => now(),
                ])->save();
            }

            SyncConflict::query()->create([
                'business_id' => $business->id,
                'sale_id' => $sale?->id,
                'entity_type' => $conflict['entity_type'],
                'entity_uuid' => $conflict['entity_uuid'],
                'reason' => $conflict['reason'],
                'message' => $conflict['message'],
            ]);
        }
    }

    private function applyOfficeStatus(SyncLink $link): void
    {
        $office = $this->gateway->office($link->server_url, (string) $link->token);
        $business = $link->business;

        if ($office === null || $business === null) {
            return;
        }

        $plan = Plan::tryFrom($office['plan']);
        $status = SubscriptionStatus::tryFrom($office['subscription_status']);

        if ($plan === null || $status === null) {
            return;
        }

        SyncContext::silence(function () use ($business, $plan, $status, $office): void {
            $business->forceFill([
                'plan' => $plan,
                'subscription_status' => $status,
                'subscription_ends_at' => $office['subscription_ends_at'],
            ])->save();
        });
    }

    private function hasLocalHistory(Business $business): bool
    {
        return Product::query()->where('business_id', $business->id)->exists()
            || Sale::query()->where('business_id', $business->id)->exists()
            || StockMovement::query()->where('business_id', $business->id)->exists();
    }

    private function serverUrl(string $serverUrl): string
    {
        $trimmed = rtrim(trim($serverUrl), '/');

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'server_url' => 'Enter the shop server address.',
            ]);
        }

        return $trimmed;
    }
}
