<?php

namespace App\Services\Sync;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SyncIdentity;
use App\Models\SyncLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncApplier
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /**
     * @param  array{
     *     id?: int,
     *     uuid: string,
     *     device_uuid?: string,
     *     entity_type: string,
     *     entity_uuid: string,
     *     op: string,
     *     payload?: array<string, mixed>
     * }  $operation
     */
    public function apply(Business $business, SyncLink $link, array $operation): string
    {
        if (($operation['device_uuid'] ?? null) === $link->device_uuid) {
            return 'skipped';
        }

        $class = SyncCatalog::classForTable($operation['entity_type']);

        if ($class === null) {
            return 'skipped';
        }

        $payload = $operation['payload'] ?? [];
        $remoteUuid = $operation['entity_uuid'];
        $op = $operation['op'];

        return SyncContext::silence(function () use ($business, $class, $payload, $remoteUuid, $op): string {
            if ($class === StockMovement::class) {
                return $op === 'delete'
                    ? 'skipped'
                    : $this->applyStockMovement($business, $remoteUuid, $payload);
            }

            if ($class === Business::class) {
                return $this->applyBusiness($business, $remoteUuid, $payload);
            }

            if ($class === User::class) {
                return $op === 'delete'
                    ? 'skipped'
                    : $this->applyUser($business, $remoteUuid, $payload);
            }

            if ($op === 'delete') {
                return $this->applyDelete($business, $class, $remoteUuid);
            }

            return $this->applyUpsert($business, $class, $remoteUuid, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyStockMovement(Business $business, string $remoteUuid, array $payload): string
    {
        $localUuid = $this->localUuid($business, $remoteUuid);

        if (StockMovement::query()
            ->where('business_id', $business->id)
            ->where('public_uuid', $localUuid)
            ->exists()) {
            return 'skipped';
        }

        $delta = (int) ($payload['quantity_delta'] ?? 0);

        if ($delta === 0) {
            return 'skipped';
        }

        $branchId = $this->resolveId($business, $payload['branch_uuid'] ?? null, Branch::class);
        $productId = $this->resolveId($business, $payload['product_uuid'] ?? null, Product::class);

        if ($branchId === null || $productId === null) {
            return 'deferred';
        }

        $userId = $this->resolveId($business, $payload['user_uuid'] ?? null, User::class);

        return DB::transaction(function () use ($business, $localUuid, $payload, $delta, $branchId, $productId, $userId): string {
            $balance = InventoryBalance::query()
                ->where('business_id', $business->id)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $balance = InventoryBalance::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'quantity' => 0,
                ]);

                $balance = InventoryBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            }

            $before = (int) $balance->quantity;
            $after = $before + $delta;

            if ($after < 0) {
                return 'deferred';
            }

            $balance->update(['quantity' => $after]);

            $reference = $this->resolveReference($business, $payload);

            $movement = new StockMovement;
            $movement->forceFill([
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'user_id' => $userId,
                'type' => is_string($payload['type'] ?? null) ? $payload['type'] : StockMovementType::OpeningStock->value,
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'unit_cost' => isset($payload['unit_cost']) ? (int) $payload['unit_cost'] : null,
                'reason' => $payload['reason'] ?? null,
                'note' => $payload['note'] ?? null,
                'reference_type' => $reference['type'] ?? null,
                'reference_id' => $reference['id'] ?? null,
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
                'sync_local_only' => false,
                'public_uuid' => $localUuid,
                'created_at' => $payload['created_at'] ?? now(),
            ]);
            $movement->save();

            return 'applied';
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyBusiness(Business $business, string $remoteUuid, array $payload): string
    {
        $localUuid = $this->localUuid($business, $remoteUuid);

        if ($business->public_uuid !== $remoteUuid && $business->public_uuid !== $localUuid) {
            return 'skipped';
        }

        $attributes = [];

        foreach (SyncCatalog::businessFields() as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = $payload[$field];
            }
        }

        $ownerUuid = $payload['owner_user_uuid'] ?? null;

        if (is_string($ownerUuid) && $ownerUuid !== '') {
            $ownerId = $this->resolveId($business, $ownerUuid, User::class);

            if ($ownerId === null) {
                return 'deferred';
            }

            $attributes['owner_user_id'] = $ownerId;
        }

        if ($attributes !== []) {
            $business->forceFill($attributes)->save();
        }

        return 'applied';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyUser(Business $business, string $remoteUuid, array $payload): string
    {
        $email = $payload['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return 'skipped';
        }

        $localUuid = $this->localUuid($business, $remoteUuid);
        $existing = User::query()->where('public_uuid', $localUuid)->first();

        if ($existing === null) {
            $byEmail = User::query()->where('email', $email)->first();

            if ($byEmail !== null) {
                $existing = $byEmail;

                if ($byEmail->public_uuid !== $remoteUuid) {
                    SyncIdentity::query()->updateOrCreate(
                        [
                            'business_id' => $business->id,
                            'remote_uuid' => $remoteUuid,
                        ],
                        [
                            'local_uuid' => $byEmail->public_uuid ?: $this->ensureUserUuid($byEmail),
                        ],
                    );
                    $localUuid = (string) $byEmail->public_uuid;
                }
            }
        }

        $password = $payload['password'] ?? null;
        unset($payload['password']);

        $attributes = $this->mapAttributes($business, $payload);

        if ($attributes === null) {
            return 'deferred';
        }

        $attributes['email'] = $email;
        $attributes['public_uuid'] = $localUuid;
        $attributes['is_platform_super_admin'] = false;
        unset($attributes['password']);

        if ($existing === null) {
            $attributes['current_business_id'] = $business->id;
            $attributes['email_verified_at'] = $attributes['email_verified_at'] ?? now();
            $attributes['password'] = Str::random(40);
            $existing = new User;
        }

        $existing->forceFill($this->onlyColumns($existing, $attributes))->save();

        if (is_string($password) && $password !== '') {
            DB::table('users')->where('id', $existing->id)->update(['password' => $password]);
        }

        return 'applied';
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function applyDelete(Business $business, string $class, string $remoteUuid): string
    {
        $model = $class::query()
            ->where('public_uuid', $this->localUuid($business, $remoteUuid))
            ->when(
                $this->columnExists(new $class, 'business_id'),
                fn ($query) => $query->where('business_id', $business->id),
            )
            ->first();

        if ($model === null) {
            return 'skipped';
        }

        try {
            $model->delete();
        } catch (\Throwable) {
            return 'deferred';
        }

        return 'applied';
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $payload
     */
    private function applyUpsert(Business $business, string $class, string $remoteUuid, array $payload): string
    {
        $localUuid = $this->localUuid($business, $remoteUuid);
        $attributes = $this->mapAttributes($business, $payload);

        if ($attributes === null) {
            return 'deferred';
        }

        $secrets = [];

        foreach (['approval_pin', 'password'] as $secret) {
            if (array_key_exists($secret, $attributes)) {
                $secrets[$secret] = $attributes[$secret];
                unset($attributes[$secret]);
            }
        }

        $existing = $class::query()
            ->where('public_uuid', $localUuid)
            ->when(
                $this->columnExists(new $class, 'business_id'),
                fn ($query) => $query->where('business_id', $business->id),
            )
            ->first();
        $model = $existing ?? new $class;
        $attributes['public_uuid'] = $localUuid;

        if ($this->columnExists($model, 'business_id')) {
            $attributes['business_id'] = $business->id;
        }

        $attributes = $this->avoidCollisions($business, $model, $attributes, $localUuid);
        $model->forceFill($this->onlyColumns($model, $attributes));

        try {
            $model->save();
        } catch (\Throwable) {
            return 'deferred';
        }

        foreach ($secrets as $column => $value) {
            if (is_string($value) && $value !== '' && $this->columnExists($model, $column)) {
                DB::table($model->getTable())->where('id', $model->getKey())->update([
                    $column => $value,
                ]);
            }
        }

        return 'applied';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function mapAttributes(Business $business, array $payload): ?array
    {
        $attributes = [];
        $foreignKeys = SyncCatalog::foreignKeys();

        foreach ($payload as $key => $value) {
            if (in_array($key, ['baseline', 'sale_uuid', 'password'], true)) {
                if ($key === 'password') {
                    $attributes['password'] = $value;
                }

                continue;
            }

            if ($key === 'reference_uuid' || $key === 'reference_type') {
                continue;
            }

            if (str_ends_with($key, '_uuid')) {
                $idKey = substr($key, 0, -strlen('_uuid')).'_id';
                $relatedClass = $foreignKeys[$idKey] ?? null;

                if ($relatedClass === null) {
                    continue;
                }

                if ($value === null) {
                    $attributes[$idKey] = null;

                    continue;
                }

                if (! is_string($value)) {
                    return null;
                }

                $relatedId = $this->resolveId($business, $value, $relatedClass);

                if ($relatedId === null) {
                    return null;
                }

                $attributes[$idKey] = $relatedId;

                continue;
            }

            if (str_ends_with($key, '_id')) {
                continue;
            }

            $attributes[$key] = $value;
        }

        $reference = $this->resolveReference($business, $payload);

        if (is_array($reference)) {
            $attributes['reference_type'] = $reference['type'];
            $attributes['reference_id'] = $reference['id'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: class-string<Model>, id: int|string}|null
     */
    private function resolveReference(Business $business, array $payload): ?array
    {
        if (! array_key_exists('reference_uuid', $payload)) {
            return null;
        }

        $uuid = $payload['reference_uuid'];
        $type = $payload['reference_type'] ?? null;

        if ($uuid === null || $type === null) {
            return null;
        }

        if (! is_string($uuid) || ! is_string($type) || ! in_array($type, SyncCatalog::models(), true)) {
            return null;
        }

        $id = $this->resolveId($business, $uuid, $type);

        if ($id === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
        ];
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function resolveId(Business $business, mixed $uuid, string $class): int|string|null
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        if (! is_string($uuid)) {
            return null;
        }

        $local = $this->localUuid($business, $uuid);
        $query = $class::query()->where('public_uuid', $local);

        if ($class !== User::class && $this->columnExists(new $class, 'business_id')) {
            $query->where('business_id', $business->id);
        }

        $related = $query->first();

        if ($related === null) {
            return null;
        }

        $key = $related->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    private function localUuid(Business $business, string $remoteUuid): string
    {
        $mapped = SyncIdentity::query()
            ->where('business_id', $business->id)
            ->where('remote_uuid', $remoteUuid)
            ->value('local_uuid');

        return is_string($mapped) && $mapped !== '' ? $mapped : $remoteUuid;
    }

    private function ensureUserUuid(User $user): string
    {
        if (is_string($user->public_uuid) && $user->public_uuid !== '') {
            return $user->public_uuid;
        }

        $uuid = (string) Str::uuid();
        $user->forceFill(['public_uuid' => $uuid])->saveQuietly();

        return $uuid;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyColumns(Model $model, array $attributes): array
    {
        $columns = $this->columns($model->getTable());

        return array_intersect_key($attributes, array_flip($columns));
    }

    private function columnExists(Model $model, string $column): bool
    {
        return in_array($column, $this->columns($model->getTable()), true);
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        if (! isset($this->columns[$table])) {
            $this->columns[$table] = array_values(Schema::getColumnListing($table));
        }

        return $this->columns[$table];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function avoidCollisions(Business $business, Model $model, array $attributes, string $uuid): array
    {
        $checks = [
            'branches' => ['name'],
            'categories' => ['name'],
            'expense_categories' => ['name'],
            'suppliers' => ['name'],
            'products' => ['sku', 'barcode'],
            'product_packs' => ['barcode'],
            'sales' => ['sale_number', 'client_request_id'],
            'sale_returns' => ['return_number', 'client_request_id'],
        ];

        $table = $model->getTable();
        $columns = $checks[$table] ?? [];

        if (! $this->columnExists($model, 'business_id')) {
            return $attributes;
        }

        foreach ($columns as $column) {
            $value = $attributes[$column] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $taken = $model->newQuery()
                ->where('business_id', $business->id)
                ->where($column, $value)
                ->where('public_uuid', '!=', $uuid)
                ->exists();

            if (! $taken) {
                continue;
            }

            if ($column === 'client_request_id') {
                $attributes[$column] = (string) Str::uuid();

                continue;
            }

            $suffix = strtoupper(substr(str_replace('-', '', $uuid), 0, 4));
            $attributes[$column] = $value.'-'.$suffix;
        }

        return $attributes;
    }
}
