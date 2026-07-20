<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     branch_id: int,
     *     expected_at?: string|null,
     *     notes?: string|null,
     *     items: list<array{product_id: int, quantity_ordered: int, unit_cost: int}>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): PurchaseOrder
    {
        $this->assertFeature($business);

        $supplier = $this->resolveSupplier($business, (int) $data['supplier_id']);
        $branch = $this->resolveBranch($business, (int) $data['branch_id']);
        $items = $this->normalizeItems($business, $data['items']);

        return DB::transaction(function () use ($business, $supplier, $branch, $data, $items, $actor): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'business_id' => $business->id,
                'supplier_id' => $supplier->id,
                'branch_id' => $branch->id,
                'status' => PurchaseOrderStatus::Draft,
                'expected_at' => $data['expected_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $order->update([
                'reference' => 'PO-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::query()->create([
                    'business_id' => $business->id,
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            $this->audit->log(
                action: 'purchase_order.created',
                auditable: $order,
                metadata: [
                    'supplier_id' => $supplier->id,
                    'branch_id' => $branch->id,
                    'item_count' => count($items),
                    'status' => PurchaseOrderStatus::Draft->value,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $order->fresh(['items.product', 'supplier', 'branch']) ?? $order;
        });
    }

    public function send(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        $this->assertFeature($order->business);
        $this->assertStatusTransition($order, PurchaseOrderStatus::Sent);

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, PurchaseOrderStatus::Sent);

            $locked->update([
                'status' => PurchaseOrderStatus::Sent,
                'sent_by' => $actor->id,
                'sent_at' => now(),
            ]);

            $this->audit->log(
                action: 'purchase_order.sent',
                auditable: $locked,
                metadata: ['status' => PurchaseOrderStatus::Sent->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch']) ?? $locked;
        });
    }

    public function cancel(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        $this->assertFeature($order->business);
        $this->assertStatusTransition($order, PurchaseOrderStatus::Cancelled);

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, PurchaseOrderStatus::Cancelled);

            $locked->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->audit->log(
                action: 'purchase_order.cancelled',
                auditable: $locked,
                metadata: ['status' => PurchaseOrderStatus::Cancelled->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch']) ?? $locked;
        });
    }

    public function refreshReceiptStatus(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing('items');

        if (in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled], true)) {
            return $order;
        }

        $allReceived = $order->items->every(
            fn (PurchaseOrderItem $item) => $item->quantity_received >= $item->quantity_ordered,
        );
        $anyReceived = $order->items->contains(
            fn (PurchaseOrderItem $item) => $item->quantity_received > 0,
        );

        $next = match (true) {
            $allReceived => PurchaseOrderStatus::Received,
            $anyReceived => PurchaseOrderStatus::PartiallyReceived,
            default => PurchaseOrderStatus::Sent,
        };

        if ($order->status !== $next) {
            $order->update(['status' => $next]);
        }

        return $order->fresh(['items.product', 'supplier', 'branch']) ?? $order;
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::PURCHASE_ORDERS);
    }

    protected function assertStatusTransition(PurchaseOrder $order, PurchaseOrderStatus $next): void
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition purchase order from %s to %s.',
                    $order->status->value,
                    $next->value,
                ),
            ]);
        }
    }

    protected function resolveSupplier(Business $business, int $supplierId): Supplier
    {
        return Supplier::query()->forBusiness($business)->whereKey($supplierId)->firstOrFail();
    }

    protected function resolveBranch(Business $business, int $branchId): Branch
    {
        return Branch::query()->forBusiness($business)->whereKey($branchId)->firstOrFail();
    }

    /**
     * @param  list<array{product_id: int, quantity_ordered: int, unit_cost: int}>  $rawItems
     * @return list<array{product: Product, quantity_ordered: int, unit_cost: int}>
     */
    protected function normalizeItems(Business $business, array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the purchase order.',
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($rawItems as $index => $raw) {
            $productId = (int) $raw['product_id'];
            $quantity = (int) $raw['quantity_ordered'];
            $unitCost = (int) $raw['unit_cost'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_ordered" => 'Ordered quantity must be at least 1.',
                ]);
            }

            if ($unitCost < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => 'Unit cost cannot be negative.',
                ]);
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Each product can only appear once.',
                ]);
            }

            $product = Product::query()->forBusiness($business)->whereKey($productId)->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Selected product is invalid for this business.',
                ]);
            }

            $seen[$productId] = true;
            $normalized[] = [
                'product' => $product,
                'quantity_ordered' => $quantity,
                'unit_cost' => $unitCost,
            ];
        }

        return $normalized;
    }
}
