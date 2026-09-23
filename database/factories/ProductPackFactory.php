<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPack>
 */
class ProductPackFactory extends Factory
{
    protected $model = ProductPack::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'name' => 'Carton',
            'units_per_pack' => 24,
            'barcode' => null,
            'selling_price' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductPack $pack): void {
            if ($pack->business_id && $pack->product_id === null) {
                return;
            }

            if ($pack->product_id && ! $pack->business_id) {
                $product = Product::query()->find($pack->product_id);
                if ($product) {
                    $pack->business_id = $product->business_id;
                }
            }
        });
    }
}
