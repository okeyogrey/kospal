<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoiceItem>
 */
class SupplierInvoiceItemFactory extends Factory
{
    protected $model = SupplierInvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);
        $unitCost = fake()->numberBetween(100, 5000);

        return [
            'business_id' => Business::factory(),
            'supplier_invoice_id' => SupplierInvoice::factory(),
            'product_id' => Product::factory(),
            'description' => null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
        ];
    }
}
