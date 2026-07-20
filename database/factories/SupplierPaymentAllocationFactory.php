<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPaymentAllocation>
 */
class SupplierPaymentAllocationFactory extends Factory
{
    protected $model = SupplierPaymentAllocation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'supplier_payment_id' => SupplierPayment::factory(),
            'supplier_invoice_id' => SupplierInvoice::factory(),
            'amount' => fake()->numberBetween(500, 20000),
        ];
    }
}
