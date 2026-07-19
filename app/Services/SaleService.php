<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected SaleNumberGenerator $saleNumbers,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     branch_id: int,
     *     customer_id?: int|null,
     *     customer_name?: string|null,
     *     payment_method: string,
     *     payment_reference?: string|null,
     *     discount_amount?: int,
     *     notes?: string|null,
     *     client_request_id: string,
     *     items: list<array{product_id: int, quantity: int, unit_price?: int|null}>,
     * }  $data
     */
    public function complete(Business $business, array $data, User $actor): Sale
    {
        $clientRequestId = trim((string) $data['client_request_id']);

        $existing = Sale::query()
            ->forBusiness($business)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing !== null) {
            return $existing->load(['items', 'payments', 'customer', 'branch', 'cashier']);
        }

        $branch = $this->resolveBranch($business, (int) $data['branch_id']);
        $customer = $this->resolveCustomer($business, $data['customer_id'] ?? null);
        $paymentMethod = PaymentMethod::from($data['payment_method']);
        $discountAmount = max(0, (int) ($data['discount_amount'] ?? 0));
        $items = $this->normalizeItems($business, $data['items']);

        $this->assertQuantitiesAvailable($business, $branch, $items);

        $subtotal = array_sum(array_map(
            static fn (array $item): int => $item['line_total'],
            $items,
        ));

        if ($discountAmount > $subtotal) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot exceed the sale subtotal.',
            ]);
        }

        $total = $subtotal - $discountAmount;
        $customerName = $customer?->name
            ?? (isset($data['customer_name']) && trim((string) $data['customer_name']) !== ''
                ? trim((string) $data['customer_name'])
                : null);

        try {
            return DB::transaction(function () use (
                $business,
                $branch,
                $customer,
                $customerName,
                $paymentMethod,
                $discountAmount,
                $subtotal,
                $total,
                $items,
                $data,
                $actor,
                $clientRequestId,
            ): Sale {
                $duplicate = Sale::query()
                    ->forBusiness($business)
                    ->where('client_request_id', $clientRequestId)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate->load(['items', 'payments', 'customer', 'branch', 'cashier']);
                }

                $saleNumber = $this->saleNumbers->next($business);

                $sale = Sale::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer?->id,
                    'cashier_id' => $actor->id,
                    'sale_number' => $saleNumber,
                    'status' => SaleStatus::Completed,
                    'payment_method' => $paymentMethod,
                    'currency' => $business->currency,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'customer_name' => $customerName,
                    'notes' => $data['notes'] ?? null,
                    'client_request_id' => $clientRequestId,
                ]);

                foreach ($items as $item) {
                    SaleItem::query()->create([
                        'business_id' => $business->id,
                        'sale_id' => $sale->id,
                        'product_id' => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'sku' => $item['product']->sku,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                    ]);

                    $this->inventory->applyMovement(
                        business: $business,
                        branch: $branch,
                        product: $item['product'],
                        type: StockMovementType::Sale,
                        quantityDelta: -$item['quantity'],
                        actor: $actor,
                        note: 'Sale '.$saleNumber,
                        reference: $sale,
                        metadata: [
                            'sale_id' => $sale->id,
                            'sale_number' => $saleNumber,
                            'source' => 'pos',
                        ],
                    );
                }

                Payment::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'method' => $paymentMethod,
                    'amount' => $total,
                    'reference' => $data['payment_reference'] ?? null,
                    'received_by' => $actor->id,
                ]);

                $this->audit->log(
                    action: 'sale.completed',
                    auditable: $sale,
                    metadata: [
                        'sale_number' => $saleNumber,
                        'branch_id' => $branch->id,
                        'customer_id' => $customer?->id,
                        'item_count' => count($items),
                        'subtotal' => $subtotal,
                        'discount_amount' => $discountAmount,
                        'total' => $total,
                        'payment_method' => $paymentMethod->value,
                        'client_request_id' => $clientRequestId,
                    ],
                    actor: $actor,
                    businessId: $business->id,
                );

                return $sale->fresh(['items', 'payments', 'customer', 'branch', 'cashier']) ?? $sale;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Sale::query()
                ->forBusiness($business)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($existing !== null) {
                return $existing->load(['items', 'payments', 'customer', 'branch', 'cashier']);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{reason: string}  $data
     */
    public function void(Sale $sale, array $data, User $actor): Sale
    {
        $reason = trim($data['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A void reason is required.',
            ]);
        }

        if (! $sale->status->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Only completed sales can be voided.',
            ]);
        }

        $sale->loadMissing(['items.product', 'branch', 'business']);

        return DB::transaction(function () use ($sale, $reason, $actor): Sale {
            $locked = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only completed sales can be voided.',
                ]);
            }

            $locked->loadMissing(['items.product', 'branch', 'business']);

            foreach ($locked->items as $item) {
                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->branch,
                    product: $item->product,
                    type: StockMovementType::SaleVoid,
                    quantityDelta: $item->quantity,
                    actor: $actor,
                    note: 'Void '.$locked->sale_number.': '.$reason,
                    reference: $locked,
                    metadata: [
                        'sale_id' => $locked->id,
                        'sale_number' => $locked->sale_number,
                        'source' => 'sale_void',
                        'void_reason' => $reason,
                    ],
                );
            }

            $locked->update([
                'status' => SaleStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->audit->log(
                action: 'sale.voided',
                auditable: $locked,
                metadata: [
                    'sale_number' => $locked->sale_number,
                    'branch_id' => $locked->branch_id,
                    'reason' => $reason,
                    'total' => $locked->total,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items', 'payments', 'customer', 'branch', 'cashier', 'voider']) ?? $locked;
        });
    }

    protected function resolveBranch(Business $business, int $branchId): Branch
    {
        $branch = Branch::query()
            ->forBusiness($business)
            ->whereKey($branchId)
            ->where('is_active', true)
            ->first();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Selected branch is invalid for this business.',
            ]);
        }

        return $branch;
    }

    protected function resolveCustomer(Business $business, mixed $customerId): ?Customer
    {
        if ($customerId === null || $customerId === '') {
            return null;
        }

        $customer = Customer::query()
            ->forBusiness($business)
            ->whereKey((int) $customerId)
            ->active()
            ->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Selected customer is invalid for this business.',
            ]);
        }

        return $customer;
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_price?: int|null}>  $rawItems
     * @return list<array{product: Product, quantity: int, unit_price: int, line_total: int}>
     */
    protected function normalizeItems(Business $business, array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the sale.',
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($rawItems as $index => $raw) {
            $productId = (int) $raw['product_id'];
            $quantity = (int) $raw['quantity'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Each product can only appear once on a sale.',
                ]);
            }

            $product = Product::query()
                ->forBusiness($business)
                ->active()
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Selected product is invalid for this business.',
                ]);
            }

            $unitPrice = array_key_exists('unit_price', $raw) && $raw['unit_price'] !== null
                ? (int) $raw['unit_price']
                : (int) $product->selling_price;

            if ($unitPrice < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => 'Unit price cannot be negative.',
                ]);
            }

            $seen[$productId] = true;
            $normalized[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{product: Product, quantity: int, unit_price: int, line_total: int}>  $items
     */
    protected function assertQuantitiesAvailable(Business $business, Branch $branch, array $items): void
    {
        foreach ($items as $index => $item) {
            $available = (int) InventoryBalance::query()
                ->where('business_id', $business->id)
                ->where('branch_id', $branch->id)
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
