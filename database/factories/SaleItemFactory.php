<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(100, 5000);

        return [
            'business_id' => Business::factory(),
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'quantity' => $quantity,
            'returned_quantity' => 0,
            'unit_price' => $unitPrice,
            'list_unit_price' => $unitPrice,
            'unit_cost' => 0,
            'negotiated_difference' => 0,
            'profit' => $quantity * $unitPrice,
            'margin_bps' => 10000,
            'manager_approved' => false,
            'line_total' => $quantity * $unitPrice,
        ];
    }
}
