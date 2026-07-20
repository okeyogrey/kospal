<?php

namespace App\Services;

use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\ProductCostHistory;
use Illuminate\Support\Facades\DB;

class InventoryValuationService
{
    /**
     * @return array{
     *     total_value: int,
     *     total_quantity: int,
     *     product_count: int,
     *     by_branch: list<array{branch_id: int, branch_name: string, quantity: int, value: int}>,
     *     products: list<array{
     *         product_id: int,
     *         product_name: string,
     *         sku: string|null,
     *         cost_price: int,
     *         quantity: int,
     *         value: int
     *     }>,
     * }
     */
    public function summarize(Business $business, ?int $branchId = null): array
    {
        $rows = InventoryBalance::query()
            ->forBusiness($business)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('branches', 'branches.id', '=', 'inventory_balances.branch_id')
            ->where('products.business_id', $business->id)
            ->select([
                'inventory_balances.branch_id',
                'branches.name as branch_name',
                'inventory_balances.product_id',
                'products.name as product_name',
                'products.sku',
                'products.cost_price',
                'inventory_balances.quantity',
                DB::raw('(inventory_balances.quantity * products.cost_price) as value'),
            ])
            ->orderByDesc('value')
            ->get();

        $byBranch = $rows
            ->groupBy('branch_id')
            ->map(fn ($group, $id) => [
                'branch_id' => (int) $id,
                'branch_name' => (string) $group->first()->branch_name,
                'quantity' => (int) $group->sum('quantity'),
                'value' => (int) $group->sum('value'),
            ])
            ->values()
            ->all();

        $products = $rows
            ->groupBy('product_id')
            ->map(fn ($group) => [
                'product_id' => (int) $group->first()->product_id,
                'product_name' => (string) $group->first()->product_name,
                'sku' => $group->first()->sku,
                'cost_price' => (int) $group->first()->cost_price,
                'quantity' => (int) $group->sum('quantity'),
                'value' => (int) $group->sum('value'),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(100)
            ->all();

        return [
            'total_value' => (int) $rows->sum('value'),
            'total_quantity' => (int) $rows->sum('quantity'),
            'product_count' => $rows->pluck('product_id')->unique()->count(),
            'by_branch' => $byBranch,
            'products' => $products,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     product_id: int,
     *     product_name: string|null,
     *     previous_cost: int,
     *     new_cost: int,
     *     quantity_on_hand: int,
     *     quantity_received: int|null,
     *     received_unit_cost: int|null,
     *     source: string|null,
     *     user_name: string|null,
     *     created_at: string|null
     * }>
     */
    public function costHistory(Business $business, ?int $productId = null, int $limit = 50): array
    {
        return ProductCostHistory::query()
            ->forBusiness($business)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->with(['product:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProductCostHistory $row) => [
                'id' => $row->id,
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name,
                'previous_cost' => $row->previous_cost,
                'new_cost' => $row->new_cost,
                'quantity_on_hand' => $row->quantity_on_hand,
                'quantity_received' => $row->quantity_received,
                'received_unit_cost' => $row->received_unit_cost,
                'source' => $row->source,
                'user_name' => $row->user?->name,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
