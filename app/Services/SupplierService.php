<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     contact_name?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     product_ids?: list<int>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($business, $data, $actor): Supplier {
            $supplier = Supplier::query()->create([
                'business_id' => $business->id,
                'name' => $data['name'],
                'contact_name' => $data['contact_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('product_ids', $data)) {
                $this->syncProducts($supplier, $data['product_ids'] ?? []);
            }

            $this->audit->log(
                action: 'supplier.created',
                auditable: $supplier,
                metadata: [
                    'product_ids' => $data['product_ids'] ?? [],
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $supplier->load('products');
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     contact_name?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     product_ids?: list<int>,
     * }  $data
     */
    public function update(Supplier $supplier, array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $actor): Supplier {
            $productIds = $data['product_ids'] ?? null;
            unset($data['product_ids']);

            $supplier->update($data);

            if (is_array($productIds)) {
                $this->syncProducts($supplier, $productIds);
            }

            $this->audit->log(
                action: 'supplier.updated',
                auditable: $supplier,
                metadata: [
                    ...$data,
                    'product_ids' => $productIds,
                ],
                actor: $actor,
                businessId: $supplier->business_id,
            );

            return $supplier->refresh()->load('products');
        });
    }

    public function delete(Supplier $supplier, User $actor): void
    {
        $businessId = $supplier->business_id;
        $payload = ['supplier_id' => $supplier->id, 'name' => $supplier->name];
        $supplier->products()->detach();
        $supplier->delete();

        $this->audit->log(
            action: 'supplier.deleted',
            metadata: $payload,
            actor: $actor,
            businessId: $businessId,
        );
    }

    /**
     * @param  list<int>  $productIds
     */
    public function syncProducts(Supplier $supplier, array $productIds): void
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            $supplier->products()->detach();

            return;
        }

        $validIds = Product::query()
            ->forBusiness($supplier->business_id)
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($productIds)) {
            throw ValidationException::withMessages([
                'product_ids' => 'One or more products are invalid for this business.',
            ]);
        }

        $sync = [];
        foreach ($validIds as $productId) {
            $sync[$productId] = [
                'business_id' => $supplier->business_id,
                'is_preferred' => false,
            ];
        }

        $supplier->products()->sync($sync);
    }
}
