<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\SyncOutbox;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncRecorder
{
    public function __construct(
        protected SyncLinkIndex $links,
    ) {}

    public function record(Model $model, string $op): void
    {
        if (SyncContext::silenced()) {
            return;
        }

        if ($model instanceof StockMovement && (bool) $model->getAttribute('sync_local_only')) {
            return;
        }

        if (! SyncCatalog::hasPublicUuidColumn($model->getTable()) && ! $model instanceof Business) {
            return;
        }

        foreach ($this->businessesFor($model) as $business) {
            if (! $this->links->has($business->id)) {
                continue;
            }

            $this->enqueue($business, $model, $op);
        }
    }

    /**
     * Copy the current shop into the outbox so another computer can catch up.
     * Stock on hand is sent as a baseline, not by replaying old movements.
     */
    public function snapshot(Business $business): void
    {
        foreach (SyncCatalog::models() as $class) {
            if (in_array($class, [User::class, Business::class, StockMovement::class], true)) {
                continue;
            }

            if (! Schema::hasTable((new $class)->getTable())) {
                continue;
            }

            $class::query()
                ->where('business_id', $business->id)
                ->orderBy('id')
                ->each(function (Model $model) use ($business): void {
                    $this->enqueue($business, $model, 'upsert');
                });
        }

        $userIds = BusinessMembership::query()
            ->where('business_id', $business->id)
            ->pluck('user_id')
            ->push($business->owner_user_id)
            ->filter()
            ->unique()
            ->values();

        User::query()
            ->whereIn('id', $userIds)
            ->orderBy('id')
            ->each(function (User $user) use ($business): void {
                $this->enqueue($business, $user, 'upsert');
            });

        $this->enqueue($business, $business, 'upsert');

        InventoryBalance::query()
            ->where('business_id', $business->id)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->each(function (InventoryBalance $balance) use ($business): void {
                $branch = $balance->branch;
                $product = $balance->product;

                if ($branch === null || $product === null) {
                    return;
                }

                $this->enqueueRaw(
                    $business,
                    'stock_movements',
                    (string) Str::uuid(),
                    'upsert',
                    [
                        'baseline' => true,
                        'type' => 'opening_stock',
                        'quantity_delta' => (int) $balance->quantity,
                        'branch_uuid' => $this->ensureUuid($branch),
                        'product_uuid' => $this->ensureUuid($product),
                        'note' => 'Stock on hand when this shop was linked',
                    ],
                );
            });
    }

    /**
     * Record pivot rows that Eloquent sync() inserts without model events.
     *
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $scope
     * @param  callable(): void  $mutate
     */
    public function track(string $class, array $scope, callable $mutate): void
    {
        /** @var Collection<int, Model> $before */
        $before = $class::query()->where($scope)->get();

        foreach ($before as $row) {
            $this->ensureUuid($row);
        }

        $before = $class::query()->where($scope)->get();
        $mutate();

        /** @var Collection<int, Model> $after */
        $after = $class::query()->where($scope)->get();
        $afterIds = $after->modelKeys();

        foreach ($before as $row) {
            if (! in_array($row->getKey(), $afterIds, true)) {
                $this->record($row, 'delete');
            }
        }

        foreach ($after as $row) {
            $this->ensureUuid($row);
            $this->record($row, 'upsert');
        }
    }

    public function ensureUuid(Model $model): string
    {
        $current = $model->getAttribute('public_uuid');

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $uuid = (string) Str::uuid();

        SyncContext::silence(function () use ($model, $uuid): void {
            $model->setAttribute('public_uuid', $uuid);
            $model->saveQuietly();
        });

        return $uuid;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueueRaw(Business $business, string $entityType, string $entityUuid, string $op, array $payload): void
    {
        SyncOutbox::query()
            ->where('business_id', $business->id)
            ->whereNull('pushed_at')
            ->where('entity_type', $entityType)
            ->where('entity_uuid', $entityUuid)
            ->delete();

        SyncOutbox::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'op' => $op,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function enqueue(Business $business, Model $model, string $op): void
    {
        $entityUuid = $this->ensureUuid($model);

        $this->enqueueRaw(
            $business,
            $model->getTable(),
            $entityUuid,
            $op,
            $op === 'delete' ? [] : $this->payload($model),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Model $model): array
    {
        $payload = [];
        $foreignKeys = SyncCatalog::foreignKeys();
        $excluded = SyncCatalog::excludedAttributes();
        $referenceHandled = false;

        foreach ($model->getAttributes() as $key => $value) {
            if (in_array($key, $excluded, true) || $key === 'public_uuid') {
                continue;
            }

            if ($key === 'reference_type' || $key === 'reference_id') {
                if ($referenceHandled) {
                    continue;
                }

                $referenceHandled = true;
                $reference = $this->referencePayload($model);

                if ($reference !== null) {
                    $payload['reference_type'] = $reference['type'];
                    $payload['reference_uuid'] = $reference['uuid'];

                    if ($reference['type'] === Sale::class) {
                        $payload['sale_uuid'] = $reference['uuid'];
                    }
                }

                continue;
            }

            if (str_ends_with($key, '_id') && isset($foreignKeys[$key])) {
                if ($value === null) {
                    $payload[substr($key, 0, -3).'_uuid'] = null;

                    continue;
                }

                $relatedClass = $foreignKeys[$key];
                $related = $relatedClass::query()->find($value);

                if ($related instanceof Model) {
                    $payload[substr($key, 0, -3).'_uuid'] = $this->ensureUuid($related);
                }

                continue;
            }

            if (str_ends_with($key, '_id')) {
                continue;
            }

            $payload[$key] = $this->scalar($value);
        }

        if ($model instanceof User) {
            $password = $model->getAttributes()['password'] ?? null;

            if (is_string($password) && $password !== '') {
                $payload['password'] = $password;
            }
        }

        return $payload;
    }

    /**
     * @return array{type: class-string<Model>, uuid: string}|null
     */
    private function referencePayload(Model $model): ?array
    {
        $type = $model->getAttribute('reference_type');
        $id = $model->getAttribute('reference_id');

        if (! is_string($type) || ! class_exists($type) || ! is_subclass_of($type, Model::class) || $id === null) {
            return null;
        }

        if (! in_array($type, SyncCatalog::models(), true)) {
            return null;
        }

        $related = $type::query()->find($id);

        if (! $related instanceof Model) {
            return null;
        }

        return [
            'type' => $type,
            'uuid' => $this->ensureUuid($related),
        ];
    }

    private function scalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * @return list<Business>
     */
    private function businessesFor(Model $model): array
    {
        if ($model instanceof Business) {
            return [$model];
        }

        if ($model instanceof User) {
            $ids = $model->memberships()->pluck('business_id');

            return array_values(Business::query()->whereIn('id', $ids)->get()->all());
        }

        $businessId = $model->getAttribute('business_id');

        if (! is_numeric($businessId)) {
            return [];
        }

        $business = Business::query()->find((int) $businessId);

        return $business instanceof Business ? [$business] : [];
    }
}
