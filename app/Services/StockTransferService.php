<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Plans\PlanLimitChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PlanLimitChecker $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     source_branch_id: int,
     *     destination_branch_id: int,
     *     notes?: string|null,
     *     items: list<array{product_id: int, quantity: int}>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): StockTransfer
    {
        $this->assertFeature($business);

        $source = $this->resolveBranch($business, (int) $data['source_branch_id']);
        $destination = $this->resolveBranch($business, (int) $data['destination_branch_id']);

        $this->assertDistinctBranches($source, $destination);

        $items = $this->normalizeItems($business, $data['items']);
        $this->assertQuantitiesAvailable($business, $source, $items);

        return DB::transaction(function () use ($business, $source, $destination, $data, $items, $actor): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'business_id' => $business->id,
                'source_branch_id' => $source->id,
                'destination_branch_id' => $destination->id,
                'status' => StockTransferStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $transfer->update([
                'reference' => 'TRF-'.str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $item) {
                StockTransferItem::query()->create([
                    'business_id' => $business->id,
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                ]);
            }

            $this->audit->log(
                action: 'stock_transfer.created',
                auditable: $transfer,
                metadata: [
                    'source_branch_id' => $source->id,
                    'destination_branch_id' => $destination->id,
                    'item_count' => count($items),
                    'status' => StockTransferStatus::Draft->value,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $transfer->fresh(['items.product', 'sourceBranch', 'destinationBranch']) ?? $transfer;
        });
    }

    public function dispatch(StockTransfer $transfer, User $actor): StockTransfer
    {
        $this->assertFeature($transfer->business);
        $this->assertStatusTransition($transfer, StockTransferStatus::Dispatched);

        $transfer->loadMissing(['items.product', 'sourceBranch', 'destinationBranch', 'business']);

        $items = $transfer->items->map(fn (StockTransferItem $item) => [
            'product' => $item->product,
            'quantity' => $item->quantity,
        ])->all();

        $this->assertQuantitiesAvailable($transfer->business, $transfer->sourceBranch, $items);

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatusTransition($locked, StockTransferStatus::Dispatched);

            $locked->loadMissing(['items.product', 'sourceBranch', 'business']);

            foreach ($locked->items as $item) {
                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->sourceBranch,
                    product: $item->product,
                    type: StockMovementType::TransferOut,
                    quantityDelta: -$item->quantity,
                    actor: $actor,
                    note: 'Transfer '.$locked->reference.' dispatched',
                    reference: $locked,
                    metadata: [
                        'stock_transfer_id' => $locked->id,
                        'destination_branch_id' => $locked->destination_branch_id,
                        'source' => 'stock_transfer_dispatch',
                    ],
                );
            }

            $locked->update([
                'status' => StockTransferStatus::Dispatched,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
            ]);

            $this->audit->log(
                action: 'stock_transfer.dispatched',
                auditable: $locked,
                metadata: [
                    'source_branch_id' => $locked->source_branch_id,
                    'destination_branch_id' => $locked->destination_branch_id,
                    'item_count' => $locked->items->count(),
                    'status' => StockTransferStatus::Dispatched->value,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'sourceBranch', 'destinationBranch']) ?? $locked;
        });
    }

    public function receive(StockTransfer $transfer, User $actor): StockTransfer
    {
        $this->assertFeature($transfer->business);
        $this->assertStatusTransition($transfer, StockTransferStatus::Received);

        $transfer->loadMissing(['items.product', 'destinationBranch', 'business']);

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatusTransition($locked, StockTransferStatus::Received);

            $locked->loadMissing(['items.product', 'destinationBranch', 'business']);

            foreach ($locked->items as $item) {
                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->destinationBranch,
                    product: $item->product,
                    type: StockMovementType::TransferIn,
                    quantityDelta: $item->quantity,
                    actor: $actor,
                    note: 'Transfer '.$locked->reference.' received',
                    reference: $locked,
                    metadata: [
                        'stock_transfer_id' => $locked->id,
                        'source_branch_id' => $locked->source_branch_id,
                        'source' => 'stock_transfer_receive',
                    ],
                );
            }

            $locked->update([
                'status' => StockTransferStatus::Received,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            $this->audit->log(
                action: 'stock_transfer.received',
                auditable: $locked,
                metadata: [
                    'source_branch_id' => $locked->source_branch_id,
                    'destination_branch_id' => $locked->destination_branch_id,
                    'item_count' => $locked->items->count(),
                    'status' => StockTransferStatus::Received->value,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'sourceBranch', 'destinationBranch']) ?? $locked;
        });
    }

    public function cancel(StockTransfer $transfer, User $actor): StockTransfer
    {
        $this->assertFeature($transfer->business);
        $this->assertStatusTransition($transfer, StockTransferStatus::Cancelled);

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatusTransition($locked, StockTransferStatus::Cancelled);

            $locked->update([
                'status' => StockTransferStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->audit->log(
                action: 'stock_transfer.cancelled',
                auditable: $locked,
                metadata: [
                    'source_branch_id' => $locked->source_branch_id,
                    'destination_branch_id' => $locked->destination_branch_id,
                    'status' => StockTransferStatus::Cancelled->value,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'sourceBranch', 'destinationBranch']) ?? $locked;
        });
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, 'stock_transfers');
    }

    protected function assertStatusTransition(StockTransfer $transfer, StockTransferStatus $next): void
    {
        if (! $transfer->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition transfer from %s to %s.',
                    $transfer->status->value,
                    $next->value,
                ),
            ]);
        }
    }

    protected function assertDistinctBranches(Branch $source, Branch $destination): void
    {
        if ($source->id === $destination->id) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'Source and destination branches must be different.',
            ]);
        }
    }

    protected function resolveBranch(Business $business, int $branchId): Branch
    {
        return Branch::query()
            ->forBusiness($business)
            ->whereKey($branchId)
            ->firstOrFail();
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $rawItems
     * @return list<array{product: Product, quantity: int}>
     */
    protected function normalizeItems(Business $business, array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to transfer.',
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($rawItems as $index => $raw) {
            $productId = (int) $raw['product_id'];
            $quantity = (int) $raw['quantity'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Transfer quantity must be at least 1.',
                ]);
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Each product can only appear once on a transfer.',
                ]);
            }

            $product = Product::query()
                ->forBusiness($business)
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Selected product is invalid for this business.',
                ]);
            }

            $seen[$productId] = true;
            $normalized[] = [
                'product' => $product,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{product: Product, quantity: int}>  $items
     */
    protected function assertQuantitiesAvailable(Business $business, Branch $source, array $items): void
    {
        foreach ($items as $index => $item) {
            $available = (int) InventoryBalance::query()
                ->where('business_id', $business->id)
                ->where('branch_id', $source->id)
                ->where('product_id', $item['product']->id)
                ->value('quantity');

            if ($item['quantity'] > $available) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => sprintf(
                        'Insufficient stock for %s. Available: %d.',
                        $item['product']->name,
                        $available,
                    ),
                ]);
            }
        }
    }
}
