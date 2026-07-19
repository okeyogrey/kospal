<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     sku: string,
     *     barcode?: string|null,
     *     category_id?: int|null,
     *     description?: string|null,
     *     cost_price: int,
     *     selling_price: int,
     *     reorder_level?: int,
     *     is_active?: bool,
     *     supplier_ids?: list<int>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($business, $data, $actor): Product {
            $product = Product::query()->create([
                'business_id' => $business->id,
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'description' => $data['description'] ?? null,
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'reorder_level' => $data['reorder_level'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('supplier_ids', $data)) {
                $this->syncSuppliers($product, $data['supplier_ids'] ?? []);
            }

            $this->audit->log(
                action: 'product.created',
                auditable: $product,
                metadata: [
                    'sku' => $product->sku,
                    'supplier_ids' => $data['supplier_ids'] ?? [],
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $product->load(['category', 'suppliers']);
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     sku?: string,
     *     barcode?: string|null,
     *     category_id?: int|null,
     *     description?: string|null,
     *     cost_price?: int,
     *     selling_price?: int,
     *     reorder_level?: int,
     *     is_active?: bool,
     *     supplier_ids?: list<int>,
     * }  $data
     */
    public function update(Product $product, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($product, $data, $actor): Product {
            $supplierIds = $data['supplier_ids'] ?? null;
            unset($data['supplier_ids']);

            $product->update($data);

            if (is_array($supplierIds)) {
                $this->syncSuppliers($product, $supplierIds);
            }

            $this->audit->log(
                action: 'product.updated',
                auditable: $product,
                metadata: [
                    ...$data,
                    'supplier_ids' => $supplierIds,
                ],
                actor: $actor,
                businessId: $product->business_id,
            );

            return $product->refresh()->load(['category', 'suppliers']);
        });
    }

    public function delete(Product $product, User $actor): void
    {
        if ($product->inventoryBalances()->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'product' => 'Cannot delete a product that still has stock at any branch.',
            ]);
        }

        DB::transaction(function () use ($product, $actor): void {
            $businessId = $product->business_id;
            $payload = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
            ];

            $product->suppliers()->detach();
            $product->delete();

            $this->audit->log(
                action: 'product.deleted',
                metadata: $payload,
                actor: $actor,
                businessId: $businessId,
            );
        });
    }

    /**
     * @param  list<int>  $supplierIds
     */
    public function syncSuppliers(Product $product, array $supplierIds): void
    {
        $supplierIds = array_values(array_unique(array_map('intval', $supplierIds)));

        if ($supplierIds === []) {
            $product->suppliers()->detach();

            return;
        }

        $validIds = Supplier::query()
            ->forBusiness($product->business_id)
            ->whereIn('id', $supplierIds)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($supplierIds)) {
            throw ValidationException::withMessages([
                'supplier_ids' => 'One or more suppliers are invalid for this business.',
            ]);
        }

        $sync = [];
        foreach ($validIds as $supplierId) {
            $sync[$supplierId] = [
                'business_id' => $product->business_id,
                'is_preferred' => false,
            ];
        }

        $product->suppliers()->sync($sync);
    }
}
