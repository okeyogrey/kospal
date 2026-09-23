<?php

namespace App\Support\Catalog;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductPack;
use Illuminate\Validation\ValidationException;

class ProductPackResolver
{
    /**
     * @return array{
     *     product: Product,
     *     pack: ProductPack|null,
     *     input_quantity: int,
     *     base_quantity: int,
     *     units_per_pack: int,
     *     list_unit_price: int,
     *     unit_cost_per_base: int|null,
     * }
     */
    public function resolve(
        Business $business,
        Product $product,
        int $quantity,
        ?int $productPackId = null,
        ?int $unitCostForSelectedUnit = null,
    ): array {
        if ($product->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'product_id' => 'Product is invalid for this business.',
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $pack = null;
        $unitsPerPack = 1;

        if ($productPackId !== null) {
            $pack = ProductPack::query()
                ->forBusiness($business)
                ->whereKey($productPackId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if ($pack === null) {
                throw ValidationException::withMessages([
                    'product_pack_id' => 'Selected pack is invalid for this product.',
                ]);
            }

            $unitsPerPack = max(1, (int) $pack->units_per_pack);
        }

        $baseQuantity = $quantity * $unitsPerPack;
        $listUnitPrice = $pack
            ? $pack->effectiveSellingPrice((int) $product->selling_price)
            : (int) $product->selling_price;

        $unitCostPerBase = null;
        if ($unitCostForSelectedUnit !== null) {
            $unitCostPerBase = (int) floor($unitCostForSelectedUnit / $unitsPerPack);
        }

        return [
            'product' => $product,
            'pack' => $pack,
            'input_quantity' => $quantity,
            'base_quantity' => $baseQuantity,
            'units_per_pack' => $unitsPerPack,
            'list_unit_price' => $listUnitPrice,
            'unit_cost_per_base' => $unitCostPerBase,
        ];
    }
}
