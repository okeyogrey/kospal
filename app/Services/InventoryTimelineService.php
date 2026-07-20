<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class InventoryTimelineService
{
    /**
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     action: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     branch_id: int|null,
     *     branch_name: string|null,
     *     quantity_delta: int|null,
     *     unit_cost: int|null,
     *     user_name: string|null,
     *     note: string|null,
     *     metadata: array<string, mixed>|null,
     *     created_at: string|null,
     * }>
     */
    public function forBusiness(
        Business $business,
        ?int $branchId = null,
        ?int $productId = null,
        int $limit = 100,
    ): array {
        $movements = StockMovement::query()
            ->forBusiness($business)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->with(['product:id,name', 'branch:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => 'movement-'.$m->id,
                'kind' => 'movement',
                'action' => $m->type->value,
                'product_id' => $m->product_id,
                'product_name' => $m->product?->name,
                'branch_id' => $m->branch_id,
                'branch_name' => $m->branch?->name,
                'quantity_delta' => $m->quantity_delta,
                'unit_cost' => $m->unit_cost,
                'user_name' => $m->user?->name,
                'note' => $m->note,
                'metadata' => $m->metadata,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        $audits = AuditLog::query()
            ->forBusiness($business)
            ->where(function ($q): void {
                $q->where('action', 'like', 'inventory.%')
                    ->orWhere('action', 'like', 'stock_transfer.%')
                    ->orWhere('action', 'like', 'purchase_order.%')
                    ->orWhere('action', 'like', 'goods_received.%')
                    ->orWhere('action', 'like', 'supplier_invoice.%')
                    ->orWhere('action', 'like', 'supplier_payment.%')
                    ->orWhere('action', 'like', 'stock_count.%')
                    ->orWhere('action', 'like', 'product.cost_updated');
            })
            ->when($productId, function ($q) use ($productId): void {
                $q->where(function ($inner) use ($productId): void {
                    $inner->where('metadata->product_id', $productId)
                        ->orWhere(function ($morph) use ($productId): void {
                            $morph->where('auditable_type', (new Product)->getMorphClass())
                                ->where('auditable_id', $productId);
                        });
                });
            })
            ->when($branchId, fn ($q) => $q->where('metadata->branch_id', $branchId))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => 'audit-'.$log->id,
                'kind' => 'audit',
                'action' => $log->action,
                'product_id' => $log->metadata['product_id'] ?? null,
                'product_name' => null,
                'branch_id' => $log->metadata['branch_id'] ?? null,
                'branch_name' => null,
                'quantity_delta' => $log->metadata['quantity_delta'] ?? null,
                'unit_cost' => $log->metadata['unit_cost'] ?? null,
                'user_name' => $log->user?->name,
                'note' => $log->metadata['note'] ?? null,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        /** @var Collection<int, array<string, mixed>> $merged */
        $merged = $movements->concat($audits)
            ->sortByDesc('created_at')
            ->values()
            ->take($limit);

        return $merged->all();
    }
}
