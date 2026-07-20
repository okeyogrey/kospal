<?php

namespace App\Services;

use App\Enums\CustomerLedgerEntryType;
use App\Enums\LedgerDirection;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
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
        protected ManagerApprovalService $approvals,
        protected PricingEngine $pricing,
        protected CustomerCreditService $customerCredit,
        protected CustomerLedgerService $customerLedger,
        protected CashSessionService $cashSessions,
    ) {}

    /**
     * @param  array{
     *     branch_id: int,
     *     customer_id?: int|null,
     *     customer_name?: string|null,
     *     payment_method?: string|null,
     *     payment_reference?: string|null,
     *     payments?: list<array{method: string, amount: int, reference?: string|null, tendered_amount?: int|null, change_amount?: int|null}>,
     *     discount_amount?: int,
     *     cash_tendered?: int,
     *     change_given?: int,
     *     notes?: string|null,
     *     client_request_id: string,
     *     held_sale_id?: int|null,
     *     manager_approval?: array{pin?: string|null, login?: string|null, password?: string|null}|null,
     *     items: list<array{product_id: int, quantity: int, unit_price?: int|null, list_unit_price?: int|null}>,
     * }  $data
     */
    public function complete(Business $business, array $data, User $actor): Sale
    {
        $clientRequestId = trim((string) $data['client_request_id']);

        $existing = Sale::query()
            ->forBusiness($business)
            ->where('client_request_id', $clientRequestId)
            ->where('status', SaleStatus::Completed)
            ->first();

        if ($existing !== null) {
            return $existing->load(['items', 'payments', 'customer', 'branch', 'cashier']);
        }

        $branch = $this->resolveBranch($business, (int) $data['branch_id']);
        $customer = $this->resolveCustomer($business, $data['customer_id'] ?? null);
        $discountAmount = max(0, (int) ($data['discount_amount'] ?? 0));
        $membership = $this->membershipFor($business, $actor);
        $items = $this->normalizeItems($business, $data['items'], $membership);
        $needsApproval = $this->pricing->requiresManagerApproval($items, $discountAmount, $membership);
        $approver = $this->approvals->resolve(
            $business,
            $actor,
            $needsApproval,
            $data['manager_approval'] ?? null,
        );

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
        $payments = $this->normalizePayments($data, $total, $customer);
        $creditAmount = array_sum(array_map(
            static fn (array $payment): int => $payment['method'] === PaymentMethod::Credit ? $payment['amount'] : 0,
            $payments,
        ));
        $amountPaid = $total - $creditAmount;

        if ($creditAmount > 0) {
            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A saved customer is required for on-account credit.',
                ]);
            }

            $this->customerCredit->assertCanChargeCredit($customer, $creditAmount);
        }

        $cashTendered = max(0, (int) ($data['cash_tendered'] ?? 0));
        $changeGiven = max(0, (int) ($data['change_given'] ?? 0));

        if ($cashTendered === 0) {
            foreach ($payments as $payment) {
                if ($payment['method'] === PaymentMethod::Cash) {
                    $cashTendered += (int) ($payment['tendered_amount'] ?? 0);
                    $changeGiven += (int) ($payment['change_amount'] ?? 0);
                }
            }
        }

        $primaryMethod = $payments[0]['method'];
        $customerName = $customer?->name
            ?? (isset($data['customer_name']) && trim((string) $data['customer_name']) !== ''
                ? trim((string) $data['customer_name'])
                : null);
        $heldSaleId = isset($data['held_sale_id']) ? (int) $data['held_sale_id'] : null;
        $saleContext = $this->cashSessions->resolveSaleContext($business, $actor, $branch->id);

        try {
            return DB::transaction(function () use (
                $business,
                $branch,
                $customer,
                $customerName,
                $primaryMethod,
                $discountAmount,
                $subtotal,
                $total,
                $amountPaid,
                $creditAmount,
                $items,
                $payments,
                $cashTendered,
                $changeGiven,
                $data,
                $actor,
                $approver,
                $membership,
                $clientRequestId,
                $heldSaleId,
                $saleContext,
            ): Sale {
                $duplicate = Sale::query()
                    ->forBusiness($business)
                    ->where('client_request_id', $clientRequestId)
                    ->where('status', SaleStatus::Completed)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate->load(['items', 'payments', 'customer', 'branch', 'cashier']);
                }

                $held = null;

                if ($heldSaleId !== null) {
                    $held = Sale::query()
                        ->forBusiness($business)
                        ->whereKey($heldSaleId)
                        ->where('status', SaleStatus::Held)
                        ->lockForUpdate()
                        ->first();

                    if ($held === null) {
                        throw ValidationException::withMessages([
                            'held_sale_id' => 'Held sale was not found or is no longer available.',
                        ]);
                    }
                }

                if ($held !== null) {
                    $sale = $held;
                    $saleNumber = $sale->sale_number;
                    $sale->items()->delete();
                    $sale->payments()->delete();
                } else {
                    $saleNumber = $this->saleNumbers->next($business);
                    $sale = new Sale;
                }

                $sale->fill([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer?->id,
                    'cashier_id' => $actor->id,
                    'staff_shift_id' => $saleContext['staff_shift_id'],
                    'cash_session_id' => $saleContext['cash_session_id'],
                    'approved_by' => $approver?->id,
                    'sale_number' => $saleNumber,
                    'status' => SaleStatus::Completed,
                    'payment_method' => $primaryMethod,
                    'currency' => $business->currency,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'cash_tendered' => $cashTendered,
                    'change_given' => $changeGiven,
                    'customer_name' => $customerName,
                    'notes' => $data['notes'] ?? null,
                    'client_request_id' => $clientRequestId,
                    'held_at' => null,
                    'held_label' => null,
                    'voided_by' => null,
                    'voided_at' => null,
                    'void_reason' => null,
                ]);
                $sale->save();

                foreach ($items as $item) {
                    SaleItem::query()->create([
                        'business_id' => $business->id,
                        'sale_id' => $sale->id,
                        'product_id' => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'sku' => $item['product']->sku,
                        'quantity' => $item['quantity'],
                        'returned_quantity' => 0,
                        'unit_price' => $item['unit_price'],
                        'list_unit_price' => $item['list_unit_price'],
                        'unit_cost' => $item['unit_cost'],
                        'negotiated_difference' => $item['negotiated_difference'],
                        'profit' => $item['profit'],
                        'margin_bps' => $item['margin_bps'],
                        'manager_approved' => $approver !== null
                            && $item['unit_price'] < $this->pricing->cashierPermissionFloor($item['product'], $membership),
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

                foreach ($payments as $payment) {
                    Payment::query()->create([
                        'business_id' => $business->id,
                        'sale_id' => $sale->id,
                        'method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'tendered_amount' => $payment['tendered_amount'],
                        'change_amount' => $payment['change_amount'],
                        'reference' => $payment['reference'],
                        'received_by' => $actor->id,
                    ]);
                }

                if ($creditAmount > 0 && $customer !== null) {
                    $this->customerLedger->record(
                        business: $business,
                        customer: $customer,
                        type: CustomerLedgerEntryType::Sale,
                        direction: LedgerDirection::Debit,
                        amount: $creditAmount,
                        description: 'Credit sale '.$saleNumber,
                        reference: $sale,
                        actor: $actor,
                    );
                }

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
                        'cash_tendered' => $cashTendered,
                        'change_given' => $changeGiven,
                        'payment_method' => $primaryMethod->value,
                        'payments' => array_map(static fn (array $payment): array => [
                            'method' => $payment['method']->value,
                            'amount' => $payment['amount'],
                        ], $payments),
                        'approved_by' => $approver?->id,
                        'held_sale_id' => $heldSaleId,
                        'client_request_id' => $clientRequestId,
                        'unit_costs' => array_map(static fn (array $item): array => [
                            'product_id' => $item['product']->id,
                            'unit_cost' => $item['unit_cost'],
                        ], $items),
                    ],
                    actor: $actor,
                    businessId: $business->id,
                );

                return $sale->fresh(['items', 'payments', 'customer', 'branch', 'cashier', 'approver']) ?? $sale;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Sale::query()
                ->forBusiness($business)
                ->where('client_request_id', $clientRequestId)
                ->where('status', SaleStatus::Completed)
                ->first();

            if ($existing !== null) {
                return $existing->load(['items', 'payments', 'customer', 'branch', 'cashier']);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{
     *     branch_id: int,
     *     customer_id?: int|null,
     *     customer_name?: string|null,
     *     discount_amount?: int,
     *     notes?: string|null,
     *     held_label?: string|null,
     *     held_sale_id?: int|null,
     *     manager_approval?: array{pin?: string|null, login?: string|null, password?: string|null}|null,
     *     items: list<array{product_id: int, quantity: int, unit_price?: int|null, list_unit_price?: int|null}>,
     * }  $data
     */
    public function hold(Business $business, array $data, User $actor): Sale
    {
        $branch = $this->resolveBranch($business, (int) $data['branch_id']);
        $customer = $this->resolveCustomer($business, $data['customer_id'] ?? null);
        $discountAmount = max(0, (int) ($data['discount_amount'] ?? 0));
        $membership = $this->membershipFor($business, $actor);
        $items = $this->normalizeItems($business, $data['items'], $membership);
        $needsApproval = $this->pricing->requiresManagerApproval($items, $discountAmount, $membership);
        $approver = $this->approvals->resolve(
            $business,
            $actor,
            $needsApproval,
            $data['manager_approval'] ?? null,
        );

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
        $heldSaleId = isset($data['held_sale_id']) ? (int) $data['held_sale_id'] : null;
        $label = isset($data['held_label']) && trim((string) $data['held_label']) !== ''
            ? trim((string) $data['held_label'])
            : ('Hold '.now()->format('H:i'));

        return DB::transaction(function () use (
            $business,
            $branch,
            $customer,
            $customerName,
            $discountAmount,
            $subtotal,
            $total,
            $items,
            $data,
            $actor,
            $approver,
            $membership,
            $heldSaleId,
            $label,
        ): Sale {
            $held = null;

            if ($heldSaleId !== null) {
                $held = Sale::query()
                    ->forBusiness($business)
                    ->whereKey($heldSaleId)
                    ->where('status', SaleStatus::Held)
                    ->lockForUpdate()
                    ->first();

                if ($held === null) {
                    throw ValidationException::withMessages([
                        'held_sale_id' => 'Held sale was not found or is no longer available.',
                    ]);
                }

                $held->items()->delete();
            }

            if ($held === null) {
                $held = Sale::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer?->id,
                    'cashier_id' => $actor->id,
                    'approved_by' => $approver?->id,
                    'sale_number' => $this->saleNumbers->next($business),
                    'status' => SaleStatus::Held,
                    'payment_method' => PaymentMethod::Cash,
                    'currency' => $business->currency,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'cash_tendered' => 0,
                    'change_given' => 0,
                    'customer_name' => $customerName,
                    'notes' => $data['notes'] ?? null,
                    'client_request_id' => null,
                    'held_at' => now(),
                    'held_label' => $label,
                ]);
            } else {
                $held->update([
                    'branch_id' => $branch->id,
                    'customer_id' => $customer?->id,
                    'cashier_id' => $actor->id,
                    'approved_by' => $approver?->id,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'customer_name' => $customerName,
                    'notes' => $data['notes'] ?? null,
                    'held_at' => now(),
                    'held_label' => $label,
                ]);
            }

            foreach ($items as $item) {
                SaleItem::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $held->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'sku' => $item['product']->sku,
                    'quantity' => $item['quantity'],
                    'returned_quantity' => 0,
                    'unit_price' => $item['unit_price'],
                    'list_unit_price' => $item['list_unit_price'],
                    'unit_cost' => $item['unit_cost'],
                    'negotiated_difference' => $item['negotiated_difference'],
                    'profit' => $item['profit'],
                    'margin_bps' => $item['margin_bps'],
                    'manager_approved' => $approver !== null
                        && $item['unit_price'] < $this->pricing->cashierPermissionFloor($item['product'], $membership),
                    'line_total' => $item['line_total'],
                ]);
            }

            $this->audit->log(
                action: 'sale.held',
                auditable: $held,
                metadata: [
                    'sale_number' => $held->sale_number,
                    'held_label' => $label,
                    'branch_id' => $branch->id,
                    'item_count' => count($items),
                    'total' => $total,
                    'approved_by' => $approver?->id,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $held->fresh(['items', 'customer', 'branch', 'cashier']) ?? $held;
        });
    }

    public function resume(Sale $sale, User $actor): Sale
    {
        if (! $sale->status->isHeld()) {
            throw ValidationException::withMessages([
                'status' => 'Only held sales can be resumed.',
            ]);
        }

        $sale->loadMissing(['items', 'customer', 'branch', 'cashier']);

        $this->audit->log(
            action: 'sale.resumed',
            auditable: $sale,
            metadata: [
                'sale_number' => $sale->sale_number,
                'held_label' => $sale->held_label,
                'item_count' => $sale->items->count(),
                'total' => $sale->total,
            ],
            actor: $actor,
            businessId: $sale->business_id,
        );

        return $sale;
    }

    public function discardHeld(Sale $sale, User $actor): void
    {
        if (! $sale->status->isHeld()) {
            throw ValidationException::withMessages([
                'status' => 'Only held sales can be discarded.',
            ]);
        }

        DB::transaction(function () use ($sale, $actor): void {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isHeld()) {
                throw ValidationException::withMessages([
                    'status' => 'Only held sales can be discarded.',
                ]);
            }

            $this->audit->log(
                action: 'sale.hold_discarded',
                auditable: $locked,
                metadata: [
                    'sale_number' => $locked->sale_number,
                    'held_label' => $locked->held_label,
                    'total' => $locked->total,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            $locked->items()->delete();
            $locked->delete();
        });
    }

    /**
     * @param  array{
     *     reason: string,
     *     refund_method: string,
     *     client_request_id: string,
     *     manager_approval?: array{login?: string|null, password?: string|null}|null,
     *     items: list<array{sale_item_id: int, quantity: int}>,
     * }  $data
     */
    public function partialReturn(Sale $sale, array $data, User $actor): SaleReturn
    {
        $clientRequestId = trim((string) $data['client_request_id']);

        $existing = SaleReturn::query()
            ->where('business_id', $sale->business_id)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing !== null) {
            return $existing->load(['items', 'sale', 'cashier']);
        }

        if (! $sale->status->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Only completed sales can accept returns.',
            ]);
        }

        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A return reason is required.',
            ]);
        }

        $sale->loadMissing(['items.product', 'branch', 'business']);
        $approver = $this->approvals->resolve(
            $sale->business,
            $actor,
            true,
            $data['manager_approval'] ?? null,
        );
        $refundMethod = PaymentMethod::from($data['refund_method']);

        return DB::transaction(function () use ($sale, $data, $actor, $approver, $reason, $refundMethod, $clientRequestId): SaleReturn {
            $locked = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only completed sales can accept returns.',
                ]);
            }

            $locked->loadMissing(['items.product', 'branch', 'business']);

            $duplicate = SaleReturn::query()
                ->where('business_id', $locked->business_id)
                ->where('client_request_id', $clientRequestId)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                return $duplicate->load(['items', 'sale', 'cashier']);
            }

            $returnItems = [];
            $total = 0;

            foreach ($data['items'] as $index => $raw) {
                $saleItem = $locked->items->firstWhere('id', (int) $raw['sale_item_id']);

                if ($saleItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.sale_item_id" => 'Return item does not belong to this sale.',
                    ]);
                }

                $quantity = (int) $raw['quantity'];
                $returnable = $saleItem->returnableQuantity();

                if ($quantity < 1 || $quantity > $returnable) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'Return quantity for %s must be between 1 and %d.',
                            $saleItem->product_name,
                            $returnable,
                        ),
                    ]);
                }

                $lineTotal = $saleItem->unit_price * $quantity;
                $total += $lineTotal;
                $returnItems[] = [
                    'sale_item' => $saleItem,
                    'quantity' => $quantity,
                    'unit_price' => $saleItem->unit_price,
                    'line_total' => $lineTotal,
                    'unit_cost' => $saleItem->unit_cost,
                ];
            }

            if ($returnItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one item to return.',
                ]);
            }

            $returnNumber = $this->saleNumbers->nextReturn($locked->business);

            $saleReturn = SaleReturn::query()->create([
                'business_id' => $locked->business_id,
                'sale_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'cashier_id' => $actor->id,
                'approved_by' => $approver?->id,
                'return_number' => $returnNumber,
                'currency' => $locked->currency,
                'total' => $total,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'client_request_id' => $clientRequestId,
            ]);

            foreach ($returnItems as $item) {
                /** @var SaleItem $saleItem */
                $saleItem = $item['sale_item'];

                SaleReturnItem::query()->create([
                    'business_id' => $locked->business_id,
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'unit_cost' => $item['unit_cost'],
                ]);

                $saleItem->update([
                    'returned_quantity' => $saleItem->returned_quantity + $item['quantity'],
                ]);

                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->branch,
                    product: $saleItem->product,
                    type: StockMovementType::Return,
                    quantityDelta: $item['quantity'],
                    actor: $actor,
                    note: 'Return '.$returnNumber.': '.$reason,
                    reference: $saleReturn,
                    metadata: [
                        'sale_id' => $locked->id,
                        'sale_number' => $locked->sale_number,
                        'sale_return_id' => $saleReturn->id,
                        'return_number' => $returnNumber,
                        'source' => 'partial_return',
                    ],
                );
            }

            Payment::query()->create([
                'business_id' => $locked->business_id,
                'sale_id' => $locked->id,
                'method' => $refundMethod,
                'amount' => -$total,
                'reference' => $returnNumber,
                'notes' => 'Partial return refund',
                'received_by' => $actor->id,
            ]);

            $this->audit->log(
                action: 'sale.returned',
                auditable: $locked,
                metadata: [
                    'sale_number' => $locked->sale_number,
                    'return_number' => $returnNumber,
                    'sale_return_id' => $saleReturn->id,
                    'total' => $total,
                    'refund_method' => $refundMethod->value,
                    'reason' => $reason,
                    'approved_by' => $approver?->id,
                    'item_count' => count($returnItems),
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $saleReturn->fresh(['items', 'sale', 'cashier', 'approver']) ?? $saleReturn;
        });
    }

    public function recordReceiptReprint(Sale $sale, User $actor): void
    {
        $this->audit->log(
            action: 'sale.receipt_reprinted',
            auditable: $sale,
            metadata: [
                'sale_number' => $sale->sale_number,
                'status' => $sale->status->value,
            ],
            actor: $actor,
            businessId: $sale->business_id,
        );
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
                $restoreQty = $item->quantity - $item->returned_quantity;

                if ($restoreQty < 1) {
                    continue;
                }

                $this->inventory->applyMovement(
                    business: $locked->business,
                    branch: $locked->branch,
                    product: $item->product,
                    type: StockMovementType::SaleVoid,
                    quantityDelta: $restoreQty,
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
     * @param  list<array{product_id: int, quantity: int, unit_price?: int|null, list_unit_price?: int|null}>  $rawItems
     * @return list<array{
     *     product: Product,
     *     quantity: int,
     *     unit_price: int,
     *     list_unit_price: int,
     *     unit_cost: int,
     *     negotiated_difference: int,
     *     profit: int,
     *     margin_bps: int,
     *     line_total: int
     * }>
     */
    protected function normalizeItems(Business $business, array $rawItems, ?BusinessMembership $membership): array
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

            $listUnitPrice = array_key_exists('list_unit_price', $raw) && $raw['list_unit_price'] !== null
                ? (int) $raw['list_unit_price']
                : (int) $product->selling_price;

            $unitPrice = array_key_exists('unit_price', $raw) && $raw['unit_price'] !== null
                ? (int) $raw['unit_price']
                : $listUnitPrice;

            if ($unitPrice < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => 'Unit price cannot be negative.',
                ]);
            }

            $this->pricing->assertLinePriceAllowed(
                $product,
                $unitPrice,
                $listUnitPrice,
                $membership,
                $index,
            );

            $unitCost = (int) $product->cost_price;
            $snapshots = $this->pricing->lineSnapshots($unitPrice, $listUnitPrice, $unitCost, $quantity);

            $seen[$productId] = true;
            $normalized[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'list_unit_price' => $listUnitPrice,
                'unit_cost' => $unitCost,
                'negotiated_difference' => $snapshots['negotiated_difference'],
                'profit' => $snapshots['profit'],
                'margin_bps' => $snapshots['margin_bps'],
                'line_total' => $unitPrice * $quantity,
            ];
        }

        return $normalized;
    }

    protected function membershipFor(Business $business, User $actor): ?BusinessMembership
    {
        return BusinessMembership::query()
            ->forBusiness($business)
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array{
     *     payment_method?: string|null,
     *     payment_reference?: string|null,
     *     payments?: list<array{method: string, amount: int, reference?: string|null, tendered_amount?: int|null, change_amount?: int|null}>,
     * }  $data
     * @return list<array{method: PaymentMethod, amount: int, reference: string|null, tendered_amount: int|null, change_amount: int|null}>
     */
    protected function normalizePayments(array $data, int $total, ?Customer $customer = null): array
    {
        $rawPayments = $data['payments'] ?? null;

        if (! is_array($rawPayments) || $rawPayments === []) {
            if (! isset($data['payment_method']) || $data['payment_method'] === null || $data['payment_method'] === '') {
                throw ValidationException::withMessages([
                    'payments' => 'Add at least one payment.',
                ]);
            }

            $rawPayments = [[
                'method' => $data['payment_method'],
                'amount' => $total,
                'reference' => $data['payment_reference'] ?? null,
                'tendered_amount' => (($data['cash_tendered'] ?? 0) > 0) ? $data['cash_tendered'] : null,
                'change_amount' => (($data['change_given'] ?? 0) > 0) ? $data['change_given'] : null,
            ]];
        }

        $normalized = [];
        $sum = 0;

        foreach ($rawPayments as $index => $raw) {
            $method = PaymentMethod::from($raw['method']);
            $amount = (int) $raw['amount'];

            if ($amount < 1) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Payment amount must be at least 1.',
                ]);
            }

            $tendered = array_key_exists('tendered_amount', $raw) && $raw['tendered_amount'] !== null
                ? (int) $raw['tendered_amount']
                : null;
            $change = array_key_exists('change_amount', $raw) && $raw['change_amount'] !== null
                ? (int) $raw['change_amount']
                : null;

            if ($tendered === 0) {
                $tendered = null;
            }

            if ($change === 0) {
                $change = null;
            }

            if ($method === PaymentMethod::Credit) {
                if ($customer === null || ! $customer->credit_enabled) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.method" => 'On-account credit requires a customer with credit enabled.',
                    ]);
                }
            }

            if ($method === PaymentMethod::Cash && $tendered !== null && $tendered < $amount) {
                throw ValidationException::withMessages([
                    "payments.{$index}.tendered_amount" => 'Cash tendered must cover the cash payment amount.',
                ]);
            }

            $sum += $amount;
            $normalized[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => isset($raw['reference']) && trim((string) $raw['reference']) !== ''
                    ? trim((string) $raw['reference'])
                    : null,
                'tendered_amount' => $tendered,
                'change_amount' => $change,
            ];
        }

        if ($sum !== $total) {
            throw ValidationException::withMessages([
                'payments' => 'Payment amounts must equal the sale total.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{product: Product, quantity: int, unit_price: int, list_unit_price: int, unit_cost: int, line_total: int}>  $items
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
