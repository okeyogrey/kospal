<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\CustomerLedgerEntryType;
use App\Enums\LedgerDirection;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public function __construct(
        protected CustomerLedgerService $ledger,
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     method: string,
     *     amount: int,
     *     paid_at: string,
     *     external_reference?: string|null,
     *     notes?: string|null,
     *     allocations: list<array{sale_id: int, amount: int}>,
     * }  $data
     */
    public function record(Business $business, array $data, User $actor): CustomerPayment
    {
        $this->limits->assertHasFeature($business, Features::CUSTOMER_CREDIT);

        $customer = Customer::query()->forBusiness($business)->whereKey((int) $data['customer_id'])->firstOrFail();
        $method = PaymentMethod::from($data['method']);
        $amount = (int) $data['amount'];
        $allocations = $data['allocations'] ?? [];

        if ($method === PaymentMethod::Credit) {
            throw ValidationException::withMessages([
                'method' => 'Customer payments cannot use on-account credit.',
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Allocate the payment to at least one sale.',
            ]);
        }

        $allocationTotal = array_sum(array_map(fn (array $row) => (int) $row['amount'], $allocations));

        if ($allocationTotal !== $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must equal the sum of allocations.',
            ]);
        }

        return DB::transaction(function () use ($business, $customer, $method, $amount, $data, $allocations, $actor): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'method' => $method,
                'amount' => $amount,
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'],
                'created_by' => $actor->id,
            ]);

            $payment->update([
                'reference' => 'CPAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($allocations as $index => $row) {
                $sale = Sale::query()
                    ->forBusiness($business)
                    ->whereKey((int) $row['sale_id'])
                    ->lockForUpdate()
                    ->first();

                if ($sale === null) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.sale_id" => 'Sale not found.',
                    ]);
                }

                if ($sale->customer_id !== $customer->id) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.sale_id" => 'Sale belongs to a different customer.',
                    ]);
                }

                if (! $sale->isOpenForPayment()) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.sale_id" => 'Sale is not open for payment.',
                    ]);
                }

                $allocAmount = (int) $row['amount'];

                if ($allocAmount < 1) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                    ]);
                }

                if ($allocAmount > $sale->amountDue()) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => sprintf(
                            'Allocation exceeds amount due (%d).',
                            $sale->amountDue(),
                        ),
                    ]);
                }

                CustomerPaymentAllocation::query()->create([
                    'business_id' => $business->id,
                    'customer_payment_id' => $payment->id,
                    'sale_id' => $sale->id,
                    'amount' => $allocAmount,
                ]);

                $sale->update([
                    'amount_paid' => $sale->amount_paid + $allocAmount,
                ]);
            }

            $this->ledger->record(
                business: $business,
                customer: $customer,
                type: CustomerLedgerEntryType::Payment,
                direction: LedgerDirection::Credit,
                amount: $amount,
                description: 'Payment '.$payment->reference,
                reference: $payment,
                actor: $actor,
                entryDate: $data['paid_at'],
            );

            $this->audit->log(
                action: 'customer_payment.recorded',
                auditable: $payment,
                metadata: [
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'method' => $method->value,
                    'allocation_count' => count($allocations),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $payment->fresh(['allocations.sale', 'customer']) ?? $payment;
        });
    }
}
