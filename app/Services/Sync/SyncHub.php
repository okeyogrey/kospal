<?php

namespace App\Services\Sync;

use App\Models\SyncAccount;
use App\Models\SyncDevice;
use App\Models\SyncOperation;
use App\Models\SyncRejection;
use App\Models\SyncStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncHub
{
    /**
     * @param  array{business_public_uuid: string, business_name: string, owner_email: string, device_uuid: string, device_name: string}  $data
     * @return array{token: string, join_code: string, business_public_uuid: string, business_name: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $existing = SyncAccount::query()
                ->where('business_public_uuid', $data['business_public_uuid'])
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'server_url' => 'This shop is already linked. Use the join code from the computer that connected it.',
                ]);
            }

            $account = SyncAccount::query()->create([
                'business_public_uuid' => $data['business_public_uuid'],
                'business_name' => $data['business_name'],
                'owner_email' => $data['owner_email'],
                'join_code' => $this->makeJoinCode(),
            ]);

            return $this->issueDevice($account, $data['device_uuid'], $data['device_name']);
        });
    }

    /**
     * @param  array{join_code: string, device_uuid: string, device_name: string}  $data
     * @return array{token: string, join_code: string, business_public_uuid: string, business_name: string}
     */
    public function join(array $data): array
    {
        $code = $this->normalizeCode($data['join_code']);

        $account = SyncAccount::query()->where('join_code', $code)->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'join_code' => 'That join code was not found. Check the code on the linked computer.',
            ]);
        }

        return DB::transaction(function () use ($account, $data): array {
            return $this->issueDevice($account, $data['device_uuid'], $data['device_name']);
        });
    }

    /**
     * @param  list<array{uuid: string, entity_type: string, entity_uuid: string, op: string, payload: array<string, mixed>, occurred_at?: string|null}>  $operations
     * @return array{accepted: list<string>, conflicts: list<array{uuid: string, entity_uuid: string, entity_type: string, reason: string, message: string}>, ignored: list<string>}
     */
    public function push(SyncDevice $device, array $operations): array
    {
        return DB::transaction(function () use ($device, $operations): array {
            SyncAccount::query()->whereKey($device->sync_account_id)->lockForUpdate()->first();

            $uuids = array_map(static fn (array $op): string => $op['uuid'], $operations);

            $existingOps = array_values(SyncOperation::query()
                ->where('sync_account_id', $device->sync_account_id)
                ->whereIn('uuid', $uuids)
                ->pluck('uuid')
                ->map(fn (mixed $uuid): string => (string) $uuid)
                ->all());

            $existingRejections = SyncRejection::query()
                ->where('sync_account_id', $device->sync_account_id)
                ->whereIn('uuid', $uuids)
                ->get()
                ->keyBy('uuid');

            /** @var array<string, int> $stock */
            $stock = [];
            /** @var array<string, int> $failingOps */
            $failingOps = [];
            /** @var array<string, list<array{0: string, 1: int}>> $appliedBySale */
            $appliedBySale = [];

            foreach ($operations as $op) {
                if (in_array($op['uuid'], $existingOps, true) || $existingRejections->has($op['uuid'])) {
                    continue;
                }

                if ($op['entity_type'] !== 'stock_movements' || $op['op'] !== 'upsert') {
                    continue;
                }

                if (array_key_exists($op['uuid'], $failingOps)) {
                    continue;
                }

                if (SyncCatalog::classForTable($op['entity_type']) === null) {
                    continue;
                }

                $branch = $op['payload']['branch_uuid'] ?? null;
                $product = $op['payload']['product_uuid'] ?? null;
                $delta = (int) ($op['payload']['quantity_delta'] ?? 0);

                if (! is_string($branch) || $branch === '' || ! is_string($product) || $product === '') {
                    $failingOps[$op['uuid']] = 0;

                    continue;
                }

                $key = $branch.'|'.$product;

                if (! array_key_exists($key, $stock)) {
                    $stock[$key] = (int) (SyncStock::query()
                        ->where('sync_account_id', $device->sync_account_id)
                        ->where('branch_uuid', $branch)
                        ->where('product_uuid', $product)
                        ->value('quantity') ?? 0);
                }

                $after = $stock[$key] + $delta;
                $saleUuid = $op['payload']['sale_uuid'] ?? null;

                if ($after < 0) {
                    $available = $stock[$key];

                    if (is_string($saleUuid) && $saleUuid !== '') {
                        foreach ($appliedBySale[$saleUuid] ?? [] as [$saleKey, $saleDelta]) {
                            $stock[$saleKey] -= $saleDelta;
                        }

                        unset($appliedBySale[$saleUuid]);

                        foreach ($operations as $sibling) {
                            if (($sibling['payload']['sale_uuid'] ?? null) === $saleUuid
                                && $sibling['entity_type'] === 'stock_movements') {
                                $failingOps[$sibling['uuid']] = $available;
                            }
                        }
                    } else {
                        $failingOps[$op['uuid']] = $available;
                    }

                    continue;
                }

                $stock[$key] = $after;

                if (is_string($saleUuid) && $saleUuid !== '') {
                    $appliedBySale[$saleUuid][] = [$key, $delta];
                }
            }

            $accepted = [];
            $conflicts = [];
            $ignored = [];

            foreach ($operations as $op) {
                $uuid = $op['uuid'];

                if (in_array($uuid, $existingOps, true)) {
                    $accepted[] = $uuid;

                    continue;
                }

                $prior = $existingRejections->get($uuid);

                if ($prior instanceof SyncRejection) {
                    $conflicts[] = $this->conflictFromRejection($prior);

                    continue;
                }

                if (SyncCatalog::classForTable($op['entity_type']) === null) {
                    $ignored[] = $uuid;

                    continue;
                }

                if (array_key_exists($uuid, $failingOps)) {
                    $available = $failingOps[$uuid];
                    $message = 'Only '.$available.' left in the shared stock. Another computer already sold or moved this product. Void this sale and refund the customer. If they still have the item, receive it back into stock after the refund.';

                    $rejection = SyncRejection::query()->create([
                        'sync_account_id' => $device->sync_account_id,
                        'uuid' => $uuid,
                        'entity_type' => $op['entity_type'],
                        'entity_uuid' => $op['entity_uuid'],
                        'reason' => 'insufficient_stock',
                        'message' => $message,
                        'created_at' => now(),
                    ]);

                    $conflicts[] = $this->conflictFromRejection($rejection);

                    continue;
                }

                if ($op['entity_type'] === 'stock_movements' && $op['op'] === 'upsert') {
                    $this->applyServerStock($device->sync_account_id, $op['payload']);
                }

                SyncOperation::query()->create([
                    'sync_account_id' => $device->sync_account_id,
                    'uuid' => $uuid,
                    'device_uuid' => $device->device_uuid,
                    'entity_type' => $op['entity_type'],
                    'entity_uuid' => $op['entity_uuid'],
                    'op' => $op['op'],
                    'payload' => $op['payload'],
                    'occurred_at' => $op['occurred_at'] ?? now(),
                    'created_at' => now(),
                ]);

                $accepted[] = $uuid;
            }

            $device->forceFill(['last_seen_at' => now()])->save();

            return [
                'accepted' => $accepted,
                'conflicts' => $conflicts,
                'ignored' => $ignored,
            ];
        });
    }

    /**
     * @return list<array{id: int, uuid: string, device_uuid: string, entity_type: string, entity_uuid: string, op: string, payload: array<string, mixed>, occurred_at: string|null}>
     */
    public function pull(SyncDevice $device, int $after): array
    {
        $device->forceFill(['last_seen_at' => now()])->save();

        return array_values(SyncOperation::query()
            ->where('sync_account_id', $device->sync_account_id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(function (SyncOperation $operation): array {
                $occurredAt = $operation->getAttribute('occurred_at');
                $payload = $operation->getAttribute('payload');

                return [
                    'id' => (int) $operation->id,
                    'uuid' => (string) $operation->uuid,
                    'device_uuid' => (string) $operation->device_uuid,
                    'entity_type' => (string) $operation->entity_type,
                    'entity_uuid' => (string) $operation->entity_uuid,
                    'op' => (string) $operation->op,
                    'payload' => is_array($payload) ? $payload : [],
                    'occurred_at' => $occurredAt instanceof \DateTimeInterface
                        ? $occurredAt->format('Y-m-d H:i:s')
                        : null,
                ];
            })
            ->all());
    }

    /**
     * @return array{join_code: string}
     */
    public function regenerateJoinCode(SyncDevice $device): array
    {
        $account = $device->account;

        if ($account === null) {
            throw ValidationException::withMessages([
                'join_code' => 'This link is no longer active.',
            ]);
        }

        $code = $this->makeJoinCode();
        $account->forceFill(['join_code' => $code])->save();

        return ['join_code' => $code];
    }

    public function deviceFromToken(string $token): ?SyncDevice
    {
        return SyncDevice::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * @return array{token: string, join_code: string, business_public_uuid: string, business_name: string}
     */
    private function issueDevice(SyncAccount $account, string $deviceUuid, string $deviceName): array
    {
        $token = bin2hex(random_bytes(32));

        $device = SyncDevice::query()
            ->where('sync_account_id', $account->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($device === null) {
            $device = new SyncDevice([
                'sync_account_id' => $account->id,
                'device_uuid' => $deviceUuid,
            ]);
        }

        $device->fill([
            'name' => $deviceName,
            'token_hash' => hash('sha256', $token),
            'last_seen_at' => now(),
        ])->save();

        return [
            'token' => $token,
            'join_code' => (string) $account->join_code,
            'business_public_uuid' => (string) $account->business_public_uuid,
            'business_name' => (string) $account->business_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyServerStock(int $accountId, array $payload): void
    {
        $branch = $payload['branch_uuid'] ?? null;
        $product = $payload['product_uuid'] ?? null;
        $delta = (int) ($payload['quantity_delta'] ?? 0);

        if (! is_string($branch) || ! is_string($product) || $delta === 0) {
            return;
        }

        $row = SyncStock::query()
            ->where('sync_account_id', $accountId)
            ->where('branch_uuid', $branch)
            ->where('product_uuid', $product)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            $row = SyncStock::query()->create([
                'sync_account_id' => $accountId,
                'branch_uuid' => $branch,
                'product_uuid' => $product,
                'quantity' => 0,
            ]);

            $row = SyncStock::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
        }

        $row->update([
            'quantity' => (int) $row->quantity + $delta,
        ]);
    }

    /**
     * @return array{uuid: string, entity_uuid: string, entity_type: string, reason: string, message: string}
     */
    private function conflictFromRejection(SyncRejection $rejection): array
    {
        return [
            'uuid' => (string) $rejection->uuid,
            'entity_uuid' => (string) $rejection->entity_uuid,
            'entity_type' => (string) $rejection->entity_type,
            'reason' => (string) $rejection->reason,
            'message' => (string) $rejection->message,
        ];
    }

    private function makeJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (SyncAccount::query()->where('join_code', $code)->exists());

        return $code;
    }

    private function normalizeCode(string $code): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        return $normalized;
    }
}
