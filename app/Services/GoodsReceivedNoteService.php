<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\GoodsReceivedNoteStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PurchaseOrderService $purchaseOrders,
        protected SupplierInvoiceService $invoices,
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     branch_id: int,
     *     purchase_order_id?: int|null,
     *     notes?: string|null,
     *     create_invoice?: bool,
     *     items: list<array{product_id: int, quantity: int, unit_cost: int, purchase_order_item_id?: int|null}>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): GoodsReceivedNote
    {
        $this->assertFeature($business);

        $supplier = Supplier::query()->forBusiness($business)->whereKey((int) $data['supplier_id'])->firstOrFail();
        $branch = Branch::query()->forBusiness($business)->whereKey((int) $data['branch_id'])->firstOrFail();
        $purchaseOrder = null;

        if (! empty($data['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()
                ->forBusiness($business)
                ->whereKey((int) $data['purchase_order_id'])
                ->with('items')
                ->firstOrFail();

            if (! in_array($purchaseOrder->status, [
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::PartiallyReceived,
            ], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Only sent or partially received purchase orders can be received.',
                ]);
            }

            if ($purchaseOrder->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier must match the purchase order.',
                ]);
            }
        }

        $items = $this->normalizeItems($business, $data['items'], $purchaseOrder);

        return DB::transaction(function () use ($business, $supplier, $branch, $purchaseOrder, $data, $items, $actor): GoodsReceivedNote {
            $grn = GoodsReceivedNote::query()->create([
                'business_id' => $business->id,
                'supplier_id' => $supplier->id,
                'branch_id' => $branch->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'status' => GoodsReceivedNoteStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $grn->update([
                'reference' => 'GRN-'.str_pad((string) $grn->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $item) {
                GoodsReceivedNoteItem::query()->create([
                    'business_id' => $business->id,
                    'goods_received_note_id' => $grn->id,
                    'product_id' => $item['product']->id,
                    'purchase_order_item_id' => $item['purchase_order_item']?->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            $this->audit->log(
                action: 'goods_received.created',
                auditable: $grn,
                metadata: [
                    'supplier_id' => $supplier->id,
                    'branch_id' => $branch->id,
                    'purchase_order_id' => $purchaseOrder?->id,
                    'item_count' => count($items),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $grn->fresh(['items.product', 'supplier', 'branch', 'purchaseOrder']) ?? $grn;
        });
    }

    public function post(GoodsReceivedNote $grn, User $actor, bool $createInvoice = true): GoodsReceivedNote
    {
        $this->assertFeature($grn->business);
        $this->assertStatusTransition($grn, GoodsReceivedNoteStatus::Posted);

        return DB::transaction(function () use ($grn, $actor, $createInvoice): GoodsReceivedNote {
            $locked = GoodsReceivedNote::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, GoodsReceivedNoteStatus::Posted);
            $locked->loadMissing(['items.product', 'branch', 'business', 'purchaseOrder.items']);

            foreach ($locked->items as $item) {
                $this->inventory->receiveStockWithCost(
                    business: $locked->business,
                    branch: $locked->branch,
                    product: $item->product,
                    quantity: $item->quantity,
                    unitCost: $item->unit_cost,
                    actor: $actor,
                    type: StockMovementType::PurchaseReceipt,
                    note: 'GRN '.$locked->reference,
                    reference: $locked,
                    metadata: [
                        'goods_received_note_id' => $locked->id,
                        'source' => 'goods_received_post',
                    ],
                );

                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::query()
                        ->whereKey($item->purchase_order_item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($poItem !== null) {
                        $poItem->update([
                            'quantity_received' => $poItem->quantity_received + $item->quantity,
                        ]);
                    }
                }
            }

            $locked->update([
                'status' => GoodsReceivedNoteStatus::Posted,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            if ($locked->purchase_order_id) {
                $this->purchaseOrders->refreshReceiptStatus(
                    PurchaseOrder::query()->whereKey($locked->purchase_order_id)->firstOrFail(),
                );
            }

            if ($createInvoice) {
                $this->invoices->createFromGoodsReceivedNote($locked, $actor);
            }

            $this->audit->log(
                action: 'goods_received.posted',
                auditable: $locked,
                metadata: [
                    'item_count' => $locked->items->count(),
                    'create_invoice' => $createInvoice,
                    'status' => GoodsReceivedNoteStatus::Posted->value,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch', 'purchaseOrder', 'supplierInvoice']) ?? $locked;
        });
    }

    public function cancel(GoodsReceivedNote $grn, User $actor): GoodsReceivedNote
    {
        $this->assertFeature($grn->business);
        $this->assertStatusTransition($grn, GoodsReceivedNoteStatus::Cancelled);

        return DB::transaction(function () use ($grn, $actor): GoodsReceivedNote {
            $locked = GoodsReceivedNote::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked, GoodsReceivedNoteStatus::Cancelled);

            $locked->update([
                'status' => GoodsReceivedNoteStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->audit->log(
                action: 'goods_received.cancelled',
                auditable: $locked,
                metadata: ['status' => GoodsReceivedNoteStatus::Cancelled->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch']) ?? $locked;
        });
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::PURCHASE_ORDERS);
    }

    protected function assertStatusTransition(GoodsReceivedNote $grn, GoodsReceivedNoteStatus $next): void
    {
        if (! $grn->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition goods received note from %s to %s.',
                    $grn->status->value,
                    $next->value,
                ),
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost: int, purchase_order_item_id?: int|null}>  $rawItems
     * @return list<array{product: Product, quantity: int, unit_cost: int, purchase_order_item: PurchaseOrderItem|null}>
     */
    protected function normalizeItems(Business $business, array $rawItems, ?PurchaseOrder $purchaseOrder): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the goods received note.',
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($rawItems as $index => $raw) {
            $productId = (int) $raw['product_id'];
            $quantity = (int) $raw['quantity'];
            $unitCost = (int) $raw['unit_cost'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Received quantity must be at least 1.',
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

            $poItem = null;
            if ($purchaseOrder !== null) {
                $poItemId = isset($raw['purchase_order_item_id']) ? (int) $raw['purchase_order_item_id'] : null;
                $poItem = $purchaseOrder->items->first(
                    fn (PurchaseOrderItem $item) => $poItemId
                        ? $item->id === $poItemId
                        : $item->product_id === $productId,
                );

                if ($poItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Product is not on the purchase order.',
                    ]);
                }

                if ($quantity > $poItem->quantityOutstanding()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'Cannot receive more than outstanding quantity (%d).',
                            $poItem->quantityOutstanding(),
                        ),
                    ]);
                }
            }

            $seen[$productId] = true;
            $normalized[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'purchase_order_item' => $poItem,
            ];
        }

        return $normalized;
    }
}
