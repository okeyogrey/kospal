<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     branch_id: int,
     *     notes?: string|null,
     *     product_ids?: list<int>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): StockCount
    {
        $this->assertFeature($business);

        $branch = Branch::query()->forBusiness($business)->whereKey((int) $data['branch_id'])->firstOrFail();

        return DB::transaction(function () use ($business, $branch, $data, $actor): StockCount {
            $count = StockCount::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'status' => StockCountStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $count->update([
                'reference' => 'CNT-'.str_pad((string) $count->id, 6, '0', STR_PAD_LEFT),
            ]);

            $balances = InventoryBalance::query()
                ->where('business_id', $business->id)
                ->where('branch_id', $branch->id)
                ->when(
                    ! empty($data['product_ids']),
                    fn ($q) => $q->whereIn('product_id', $data['product_ids']),
                )
                ->with('product')
                ->get();

            foreach ($balances as $balance) {
                StockCountItem::query()->create([
                    'business_id' => $business->id,
                    'stock_count_id' => $count->id,
                    'product_id' => $balance->product_id,
                    'system_quantity' => (int) $balance->quantity,
                    'counted_quantity' => null,
                    'variance' => null,
                ]);
            }

            $this->audit->log(
                action: 'stock_count.created',
                auditable: $count,
                metadata: [
                    'branch_id' => $branch->id,
                    'item_count' => $balances->count(),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $count->fresh(['items.product', 'branch']) ?? $count;
        });
    }

    public function start(StockCount $count, User $actor): StockCount
    {
        $this->assertFeature($count->business);
        $this->assertStatusTransition($count, StockCountStatus::InProgress);

        return DB::transaction(function () use ($count, $actor): StockCount {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, StockCountStatus::InProgress);

            // Refresh system quantities at start.
            $locked->loadMissing('items');
            foreach ($locked->items as $item) {
                $systemQty = (int) InventoryBalance::query()
                    ->where('business_id', $locked->business_id)
                    ->where('branch_id', $locked->branch_id)
                    ->where('product_id', $item->product_id)
                    ->value('quantity');

                $item->update(['system_quantity' => $systemQty]);
            }

            $locked->update(['status' => StockCountStatus::InProgress]);

            $this->audit->log(
                action: 'stock_count.started',
                auditable: $locked,
                metadata: ['status' => StockCountStatus::InProgress->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'branch']) ?? $locked;
        });
    }

    /**
     * @param  list<array{product_id: int, counted_quantity: int}>  $counts
     */
    public function recordCounts(StockCount $count, array $counts, User $actor): StockCount
    {
        $this->assertFeature($count->business);

        if (! in_array($count->status, [StockCountStatus::Draft, StockCountStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'status' => 'Counts can only be recorded on draft or in-progress stock counts.',
            ]);
        }

        return DB::transaction(function () use ($count, $counts, $actor): StockCount {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('items');

            if ($locked->status === StockCountStatus::Draft) {
                $locked->update(['status' => StockCountStatus::InProgress]);
            }

            foreach ($counts as $index => $row) {
                $item = $locked->items->firstWhere('product_id', (int) $row['product_id']);

                if ($item === null) {
                    throw ValidationException::withMessages([
                        "counts.{$index}.product_id" => 'Product is not on this stock count.',
                    ]);
                }

                $counted = (int) $row['counted_quantity'];

                if ($counted < 0) {
                    throw ValidationException::withMessages([
                        "counts.{$index}.counted_quantity" => 'Counted quantity cannot be negative.',
                    ]);
                }

                $item->update([
                    'counted_quantity' => $counted,
                    'variance' => $counted - $item->system_quantity,
                ]);
            }

            $this->audit->log(
                action: 'stock_count.counts_recorded',
                auditable: $locked,
                metadata: ['lines_updated' => count($counts)],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'branch']) ?? $locked;
        });
    }

    public function complete(StockCount $count, User $actor): StockCount
    {
        $this->assertFeature($count->business);
        $this->assertStatusTransition($count, StockCountStatus::Completed);

        return DB::transaction(function () use ($count, $actor): StockCount {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, StockCountStatus::Completed);
            $locked->loadMissing(['items.product', 'branch', 'business']);

            $uncounted = $locked->items->filter(fn (StockCountItem $item) => $item->counted_quantity === null);

            if ($uncounted->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'All products must have a counted quantity before completing.',
                ]);
            }

            foreach ($locked->items as $item) {
                $variance = (int) $item->variance;

                if ($variance === 0) {
                    continue;
                }

                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->branch,
                    product: $item->product,
                    type: StockMovementType::StockCountVariance,
                    quantityDelta: $variance,
                    actor: $actor,
                    reason: StockAdjustmentReason::CountVariance,
                    note: 'Stock count '.$locked->reference,
                    reference: $locked,
                    metadata: [
                        'stock_count_id' => $locked->id,
                        'system_quantity' => $item->system_quantity,
                        'counted_quantity' => $item->counted_quantity,
                        'source' => 'stock_count_complete',
                    ],
                );
            }

            $locked->update([
                'status' => StockCountStatus::Completed,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->audit->log(
                action: 'stock_count.completed',
                auditable: $locked,
                metadata: [
                    'item_count' => $locked->items->count(),
                    'variance_lines' => $locked->items->filter(fn ($i) => (int) $i->variance !== 0)->count(),
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'branch']) ?? $locked;
        });
    }

    public function cancel(StockCount $count, User $actor): StockCount
    {
        $this->assertFeature($count->business);
        $this->assertStatusTransition($count, StockCountStatus::Cancelled);

        return DB::transaction(function () use ($count, $actor): StockCount {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, StockCountStatus::Cancelled);

            $locked->update([
                'status' => StockCountStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->audit->log(
                action: 'stock_count.cancelled',
                auditable: $locked,
                metadata: ['status' => StockCountStatus::Cancelled->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'branch']) ?? $locked;
        });
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::STOCK_COUNTS);
    }

    protected function assertStatusTransition(StockCount $count, StockCountStatus $next): void
    {
        if (! $count->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition stock count from %s to %s.',
                    $count->status->value,
                    $next->value,
                ),
            ]);
        }
    }
}
