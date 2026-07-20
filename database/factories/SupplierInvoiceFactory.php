<?php

namespace Database\Factories;

use App\Enums\SupplierInvoiceStatus;
use App\Models\Business;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);

        return [
            'business_id' => Business::factory(),
            'reference' => null,
            'supplier_invoice_number' => fake()->optional()->bothify('INV-####'),
            'supplier_id' => Supplier::factory(),
            'branch_id' => null,
            'goods_received_note_id' => null,
            'purchase_order_id' => null,
            'status' => SupplierInvoiceStatus::Draft,
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'total' => $subtotal,
            'amount_paid' => 0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => SupplierInvoiceStatus::Posted,
            'posted_at' => now(),
            'posted_by' => User::factory(),
        ]);
    }
}
