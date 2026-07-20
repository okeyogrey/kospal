<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\SupplierInvoiceStatus;
use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceService
{
    public function __construct(
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    public function createFromGoodsReceivedNote(GoodsReceivedNote $grn, User $actor): SupplierInvoice
    {
        $grn->loadMissing(['items.product', 'business']);

        $items = $grn->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->product?->name,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
        ])->all();

        return $this->create($grn->business, [
            'supplier_id' => $grn->supplier_id,
            'branch_id' => $grn->branch_id,
            'goods_received_note_id' => $grn->id,
            'purchase_order_id' => $grn->purchase_order_id,
            'invoice_date' => now()->toDateString(),
            'items' => $items,
        ], $actor, autoPost: true);
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     branch_id?: int|null,
     *     goods_received_note_id?: int|null,
     *     purchase_order_id?: int|null,
     *     supplier_invoice_number?: string|null,
     *     invoice_date?: string|null,
     *     due_date?: string|null,
     *     tax_total?: int,
     *     notes?: string|null,
     *     items: list<array{product_id?: int|null, description?: string|null, quantity: int, unit_cost: int}>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor, bool $autoPost = false): SupplierInvoice
    {
        $this->assertFeature($business);

        $supplier = Supplier::query()->forBusiness($business)->whereKey((int) $data['supplier_id'])->firstOrFail();
        $items = $this->normalizeItems($business, $data['items']);
        $subtotal = array_sum(array_map(fn (array $item) => $item['line_total'], $items));
        $taxTotal = (int) ($data['tax_total'] ?? 0);
        $total = $subtotal + $taxTotal;

        return DB::transaction(function () use ($business, $supplier, $data, $items, $subtotal, $taxTotal, $total, $actor, $autoPost): SupplierInvoice {
            $invoice = SupplierInvoice::query()->create([
                'business_id' => $business->id,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_id' => $supplier->id,
                'branch_id' => $data['branch_id'] ?? null,
                'goods_received_note_id' => $data['goods_received_note_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'status' => SupplierInvoiceStatus::Draft,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'amount_paid' => 0,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $invoice->update([
                'reference' => 'SINV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $item) {
                SupplierInvoiceItem::query()->create([
                    'business_id' => $business->id,
                    'supplier_invoice_id' => $invoice->id,
                    'product_id' => $item['product']?->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                ]);
            }

            $this->audit->log(
                action: 'supplier_invoice.created',
                auditable: $invoice,
                metadata: [
                    'supplier_id' => $supplier->id,
                    'total' => $total,
                    'item_count' => count($items),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            if ($autoPost) {
                return $this->post($invoice, $actor);
            }

            return $invoice->fresh(['items.product', 'supplier', 'branch']) ?? $invoice;
        });
    }

    public function post(SupplierInvoice $invoice, User $actor): SupplierInvoice
    {
        $this->assertFeature($invoice->business);

        if ($invoice->status !== SupplierInvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be posted.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $actor): SupplierInvoice {
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SupplierInvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft invoices can be posted.',
                ]);
            }

            $locked->update([
                'status' => SupplierInvoiceStatus::Posted,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->audit->log(
                action: 'supplier_invoice.posted',
                auditable: $locked,
                metadata: [
                    'total' => $locked->total,
                    'status' => SupplierInvoiceStatus::Posted->value,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch']) ?? $locked;
        });
    }

    public function void(SupplierInvoice $invoice, User $actor): SupplierInvoice
    {
        $this->assertFeature($invoice->business);

        if ($invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'status' => 'Cannot void an invoice that has payments allocated.',
            ]);
        }

        if (! $invoice->status->canTransitionTo(SupplierInvoiceStatus::Void)) {
            throw ValidationException::withMessages([
                'status' => 'This invoice cannot be voided.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $actor): SupplierInvoice {
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->amount_paid > 0 || ! $locked->status->canTransitionTo(SupplierInvoiceStatus::Void)) {
                throw ValidationException::withMessages([
                    'status' => 'This invoice cannot be voided.',
                ]);
            }

            $locked->update([
                'status' => SupplierInvoiceStatus::Void,
                'voided_by' => $actor->id,
                'voided_at' => now(),
            ]);

            $this->audit->log(
                action: 'supplier_invoice.voided',
                auditable: $locked,
                metadata: ['status' => SupplierInvoiceStatus::Void->value],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->fresh(['items.product', 'supplier', 'branch']) ?? $locked;
        });
    }

    public function refreshPaymentStatus(SupplierInvoice $invoice): SupplierInvoice
    {
        if (in_array($invoice->status, [SupplierInvoiceStatus::Draft, SupplierInvoiceStatus::Void], true)) {
            return $invoice;
        }

        $next = match (true) {
            $invoice->amount_paid <= 0 => SupplierInvoiceStatus::Posted,
            $invoice->amount_paid >= $invoice->total => SupplierInvoiceStatus::Paid,
            default => SupplierInvoiceStatus::PartiallyPaid,
        };

        if ($invoice->status !== $next) {
            $invoice->update(['status' => $next]);
        }

        return $invoice->fresh() ?? $invoice;
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::PURCHASE_ORDERS);
    }

    /**
     * @param  list<array{product_id?: int|null, description?: string|null, quantity: int, unit_cost: int}>  $rawItems
     * @return list<array{product: Product|null, description: string|null, quantity: int, unit_cost: int, line_total: int}>
     */
    protected function normalizeItems(Business $business, array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one line to the invoice.',
            ]);
        }

        $normalized = [];

        foreach ($rawItems as $index => $raw) {
            $quantity = (int) $raw['quantity'];
            $unitCost = (int) $raw['unit_cost'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            if ($unitCost < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => 'Unit cost cannot be negative.',
                ]);
            }

            $product = null;
            if (! empty($raw['product_id'])) {
                $product = Product::query()->forBusiness($business)->whereKey((int) $raw['product_id'])->first();
                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Selected product is invalid.',
                    ]);
                }
            }

            $normalized[] = [
                'product' => $product,
                'description' => $raw['description'] ?? $product?->name,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $quantity * $unitCost,
            ];
        }

        return $normalized;
    }
}
