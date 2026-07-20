<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsService
{
    /**
     * @return array{
     *     lifetime_spend: int,
     *     purchase_count: int,
     *     average_order_value: int,
     *     last_purchase_at: string|null,
     *     most_purchased_products: list<array{
     *         product_id: int,
     *         product_name: string,
     *         sku: string|null,
     *         total_quantity: int,
     *         total_spend: int,
     *     }>,
     * }
     */
    public function summarize(Customer $customer, int $productLimit = 10): array
    {
        $salesStats = $customer->sales()
            ->where('status', SaleStatus::Completed)
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(total), 0) as lifetime_spend')
            ->selectRaw('MAX(created_at) as last_purchase_at')
            ->first();

        $purchaseCount = (int) ($salesStats->purchase_count ?? 0);
        $lifetimeSpend = (int) ($salesStats->lifetime_spend ?? 0);
        $averageOrderValue = $purchaseCount > 0 ? (int) round($lifetimeSpend / $purchaseCount) : 0;

        $mostPurchased = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.customer_id', $customer->id)
            ->where('sales.status', SaleStatus::Completed->value)
            ->where('sales.business_id', $customer->business_id)
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->selectRaw('sale_items.product_id')
            ->selectRaw('sale_items.product_name')
            ->selectRaw('sale_items.sku')
            ->selectRaw('SUM(sale_items.quantity) as total_quantity')
            ->selectRaw('SUM(sale_items.line_total) as total_spend')
            ->orderByDesc('total_quantity')
            ->limit($productLimit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'sku' => $row->sku,
                'total_quantity' => (int) $row->total_quantity,
                'total_spend' => (int) $row->total_spend,
            ])
            ->values()
            ->all();

        return [
            'lifetime_spend' => $lifetimeSpend,
            'purchase_count' => $purchaseCount,
            'average_order_value' => $averageOrderValue,
            'last_purchase_at' => $salesStats->last_purchase_at
                ? (string) $salesStats->last_purchase_at
                : null,
            'most_purchased_products' => $mostPurchased,
        ];
    }
}
