<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(
        protected SupplierInvoiceService $invoices,
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     method: string,
     *     amount: int,
     *     paid_at: string,
     *     external_reference?: string|null,
     *     notes?: string|null,
     *     allocations: list<array{supplier_invoice_id: int, amount: int}>,
     * }  $data
     */
    public function record(Business $business, array $data, User $actor): SupplierPayment
    {
        $this->assertFeature($business);

        $supplier = Supplier::query()->forBusiness($business)->whereKey((int) $data['supplier_id'])->firstOrFail();
        $method = PaymentMethod::from($data['method']);
        $amount = (int) $data['amount'];
        $allocations = $data['allocations'] ?? [];

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Allocate the payment to at least one invoice.',
            ]);
        }

        $allocationTotal = array_sum(array_map(fn (array $row) => (int) $row['amount'], $allocations));

        if ($allocationTotal !== $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must equal the sum of allocations.',
            ]);
        }

        return DB::transaction(function () use ($business, $supplier, $method, $amount, $data, $allocations, $actor): SupplierPayment {
            $payment = SupplierPayment::query()->create([
                'business_id' => $business->id,
                'supplier_id' => $supplier->id,
                'method' => $method,
                'amount' => $amount,
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'],
                'created_by' => $actor->id,
            ]);

            $payment->update([
                'reference' => 'SPAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($allocations as $index => $row) {
                $invoice = SupplierInvoice::query()
                    ->forBusiness($business)
                    ->whereKey((int) $row['supplier_invoice_id'])
                    ->lockForUpdate()
                    ->first();

                if ($invoice === null) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.supplier_invoice_id" => 'Invoice not found.',
                    ]);
                }

                if ($invoice->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.supplier_invoice_id" => 'Invoice belongs to a different supplier.',
                    ]);
                }

                if (! $invoice->status->isOpen()) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.supplier_invoice_id" => 'Invoice is not open for payment.',
                    ]);
                }

                $allocAmount = (int) $row['amount'];

                if ($allocAmount < 1) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                    ]);
                }

                if ($allocAmount > $invoice->amountDue()) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => sprintf(
                            'Allocation exceeds amount due (%d).',
                            $invoice->amountDue(),
                        ),
                    ]);
                }

                SupplierPaymentAllocation::query()->create([
                    'business_id' => $business->id,
                    'supplier_payment_id' => $payment->id,
                    'supplier_invoice_id' => $invoice->id,
                    'amount' => $allocAmount,
                ]);

                $invoice->update([
                    'amount_paid' => $invoice->amount_paid + $allocAmount,
                ]);

                $this->invoices->refreshPaymentStatus($invoice->fresh() ?? $invoice);
            }

            $this->audit->log(
                action: 'supplier_payment.recorded',
                auditable: $payment,
                metadata: [
                    'supplier_id' => $supplier->id,
                    'amount' => $amount,
                    'method' => $method->value,
                    'allocation_count' => count($allocations),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $payment->fresh(['allocations.supplierInvoice', 'supplier']) ?? $payment;
        });
    }

    protected function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::PURCHASE_ORDERS);
    }
}
