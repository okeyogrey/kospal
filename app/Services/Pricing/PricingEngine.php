<?php

namespace App\Services\Pricing;

use App\Models\BusinessMembership;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class PricingEngine
{
    /**
     * Lowest unit price a cashier may use without manager approval.
     * Absolute floor is always the product minimum selling price.
     */
    public function cashierPermissionFloor(Product $product, ?BusinessMembership $membership): int
    {
        $minSelling = max(0, (int) $product->min_selling_price);
        $suggested = max(0, (int) $product->selling_price);

        if ($membership === null || $membership->role->canApplySaleDiscount()) {
            return $minSelling;
        }

        $percent = max(0, min(100, (int) $membership->negotiation_floor_percent));
        $permissionFloor = (int) intdiv($suggested * $percent, 100);

        return max($minSelling, $permissionFloor);
    }

    /**
     * @throws ValidationException
     */
    public function assertLinePriceAllowed(
        Product $product,
        int $unitPrice,
        int $listUnitPrice,
        ?BusinessMembership $membership,
        int $itemIndex,
    ): void {
        $minSelling = max(0, (int) $product->min_selling_price);

        if ($unitPrice < $minSelling) {
            throw ValidationException::withMessages([
                "items.{$itemIndex}.unit_price" => sprintf(
                    'Price for %s cannot fall below the minimum selling price.',
                    $product->name,
                ),
            ]);
        }

        $priceChanged = $unitPrice !== $listUnitPrice;
        $canBypassNegotiable = $membership?->role->canApplySaleDiscount() ?? false;

        if ($priceChanged && ! $product->is_negotiable && ! $canBypassNegotiable) {
            throw ValidationException::withMessages([
                "items.{$itemIndex}.unit_price" => sprintf(
                    '%s is not negotiable. Only the suggested selling price may be used.',
                    $product->name,
                ),
            ]);
        }
    }

    /**
     * Whether a line (or sale discount) requires manager PIN/credentials.
     *
     * @param  list<array{product: Product, unit_price: int, list_unit_price: int}>  $items
     */
    public function requiresManagerApproval(
        array $items,
        int $discountAmount,
        ?BusinessMembership $membership,
    ): bool {
        if ($discountAmount > 0) {
            return true;
        }

        if ($membership !== null && $membership->role->canApplySaleDiscount()) {
            return false;
        }

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $item['product'];
            $floor = $this->cashierPermissionFloor($product, $membership);

            if ($item['unit_price'] < $floor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snapshot metrics stored on each sale line.
     *
     * @return array{
     *     negotiated_difference: int,
     *     profit: int,
     *     margin_bps: int,
     * }
     */
    public function lineSnapshots(int $unitPrice, int $listUnitPrice, int $unitCost, int $quantity): array
    {
        $negotiatedDifference = $listUnitPrice - $unitPrice;
        $profit = ($unitPrice - $unitCost) * $quantity;
        $marginBps = $unitPrice > 0
            ? (int) intdiv(($unitPrice - $unitCost) * 10000, $unitPrice)
            : 0;

        return [
            'negotiated_difference' => $negotiatedDifference,
            'profit' => $profit,
            'margin_bps' => $marginBps,
        ];
    }

    /**
     * Deep discount: more than 20% below suggested, or at/below minimum.
     */
    public function isExcessiveOverride(int $unitPrice, int $listUnitPrice, int $minSellingPrice): bool
    {
        if ($listUnitPrice <= 0) {
            return $unitPrice < $listUnitPrice;
        }

        if ($unitPrice <= $minSellingPrice && $unitPrice < $listUnitPrice) {
            return true;
        }

        $ratioBps = (int) intdiv($unitPrice * 10000, $listUnitPrice);

        return $ratioBps < 8000;
    }
}
