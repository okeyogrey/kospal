<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCostService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * Recompute weighted-average cost after receiving stock.
     *
     * newAvg = ((onHand * currentCost) + (qty * unitCost)) / (onHand + qty)
     *
     * On-hand is measured before the receipt is applied to balances.
     */
    public function recomputeWeightedAverage(
        Product $product,
        int $quantityReceived,
        int $unitCostMinor,
        User $actor,
        ?Model $reference = null,
        string $source = 'goods_received',
    ): int {
        if ($quantityReceived <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        if ($unitCostMinor < 0) {
            throw ValidationException::withMessages([
                'unit_cost' => 'Unit cost cannot be negative.',
            ]);
        }

        return DB::transaction(function () use (
            $product,
            $quantityReceived,
            $unitCostMinor,
            $actor,
            $reference,
            $source,
        ): int {
            $locked = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $onHand = (int) InventoryBalance::query()
                ->where('business_id', $locked->business_id)
                ->where('product_id', $locked->id)
                ->sum('quantity');

            // Receipt may already be applied; subtract just-received qty if balances include it.
            $onHandBeforeReceipt = max(0, $onHand - $quantityReceived);

            $previousCost = (int) $locked->cost_price;
            $numerator = ($onHandBeforeReceipt * $previousCost) + ($quantityReceived * $unitCostMinor);
            $denominator = $onHandBeforeReceipt + $quantityReceived;
            $newCost = $denominator > 0
                ? (int) intdiv($numerator, $denominator)
                : $unitCostMinor;

            if ($newCost !== $previousCost) {
                $locked->update(['cost_price' => $newCost]);
            }

            ProductCostHistory::query()->create([
                'business_id' => $locked->business_id,
                'product_id' => $locked->id,
                'previous_cost' => $previousCost,
                'new_cost' => $newCost,
                'quantity_on_hand' => $onHandBeforeReceipt,
                'quantity_received' => $quantityReceived,
                'received_unit_cost' => $unitCostMinor,
                'source' => $source,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $actor->id,
                'created_at' => now(),
            ]);

            $this->audit->log(
                action: 'product.cost_updated',
                auditable: $locked,
                metadata: [
                    'previous_cost' => $previousCost,
                    'new_cost' => $newCost,
                    'quantity_on_hand' => $onHandBeforeReceipt,
                    'quantity_received' => $quantityReceived,
                    'received_unit_cost' => $unitCostMinor,
                    'source' => $source,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $newCost;
        });
    }
}
