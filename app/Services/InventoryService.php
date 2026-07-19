<?php

namespace App\Services;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function receiveStock(
        Business $business,
        Branch $branch,
        Product $product,
        int $quantity,
        User $actor,
        ?string $note = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        $this->assertSameBusiness($business, $branch, $product);

        return $this->applyMovement(
            business: $business,
            branch: $branch,
            product: $product,
            type: StockMovementType::StockReceipt,
            quantityDelta: $quantity,
            actor: $actor,
            note: $note,
            metadata: ['source' => 'stock_receipt'],
        );
    }

    /**
     * @deprecated Use receiveStock()
     */
    public function recordOpeningStock(
        Business $business,
        Branch $branch,
        Product $product,
        int $quantity,
        User $actor,
        ?string $note = null,
    ): StockMovement {
        return $this->receiveStock($business, $branch, $product, $quantity, $actor, $note);
    }

    public function adjustStock(
        Business $business,
        Branch $branch,
        Product $product,
        int $quantityDelta,
        StockAdjustmentReason $reason,
        string $note,
        User $actor,
    ): StockMovement {
        if ($quantityDelta >= 0) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Stock adjustments must reduce quantity (use a negative amount). Record incoming stock with Receive stock instead.',
            ]);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'A note is required for stock adjustments.',
            ]);
        }

        $this->assertSameBusiness($business, $branch, $product);

        return $this->applyMovement(
            business: $business,
            branch: $branch,
            product: $product,
            type: StockMovementType::Adjustment,
            quantityDelta: $quantityDelta,
            actor: $actor,
            reason: $reason,
            note: $note,
            metadata: ['source' => 'manual_adjustment'],
        );
    }

    /**
     * Shared entry point for all inventory mutations (sales, transfers, etc.).
     */
    public function applyMovement(
        Business $business,
        Branch $branch,
        Product $product,
        StockMovementType $type,
        int $quantityDelta,
        User $actor,
        ?StockAdjustmentReason $reason = null,
        ?string $note = null,
        ?Model $reference = null,
        array $metadata = [],
    ): StockMovement {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock movement quantity cannot be zero.',
            ]);
        }

        $this->assertSameBusiness($business, $branch, $product);

        return DB::transaction(function () use (
            $business,
            $branch,
            $product,
            $type,
            $quantityDelta,
            $actor,
            $reason,
            $note,
            $reference,
            $metadata,
        ): StockMovement {
            $balance = InventoryBalance::query()
                ->where('business_id', $business->id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $balance = InventoryBalance::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                ]);

                $balance = InventoryBalance::query()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $before = (int) $balance->quantity;
            $after = $before + $quantityDelta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock. Available: '.$before.'.',
                ]);
            }

            $balance->update(['quantity' => $after]);

            $movement = StockMovement::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $reason,
                'note' => $note,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);

            $this->audit->log(
                action: 'inventory.'.$type->value,
                auditable: $movement,
                metadata: [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'quantity_delta' => $quantityDelta,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => $reason?->value,
                    'note' => $note,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $movement;
        });
    }

    protected function assertSameBusiness(Business $business, Branch $branch, Product $product): void
    {
        if ($branch->business_id !== $business->id || $product->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'product_id' => 'Product and branch must belong to the current business.',
            ]);
        }
    }
}
