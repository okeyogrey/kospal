<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductPack;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sync\SyncRecorder;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected AuditLogger $audit,
        protected SyncRecorder $sync,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     sku?: string|null,
     *     barcode?: string|null,
     *     category_id?: int|null,
     *     description?: string|null,
     *     base_unit_name?: string,
     *     cost_price: int,
     *     selling_price: int,
     *     min_selling_price?: int,
     *     is_negotiable?: bool,
     *     reorder_level?: int,
     *     is_active?: bool,
     *     supplier_ids?: list<int>,
     *     packs?: list<array{
     *         id?: int|null,
     *         name: string,
     *         units_per_pack: int,
     *         barcode?: string|null,
     *         selling_price?: int|null,
     *         is_active?: bool,
     *     }>,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($business, $data, $actor): Product {
            $sku = trim((string) ($data['sku'] ?? ''));
            if ($sku === '') {
                $sku = $this->generateSku($business);
            }

            $product = Product::query()->create([
                'business_id' => $business->id,
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'sku' => $sku,
                'barcode' => $data['barcode'] ?? null,
                'description' => $data['description'] ?? null,
                'base_unit_name' => $this->normalizeBaseUnitName($data['base_unit_name'] ?? null),
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'min_selling_price' => $data['min_selling_price'] ?? $data['cost_price'],
                'is_negotiable' => $data['is_negotiable'] ?? true,
                'reorder_level' => $data['reorder_level'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('supplier_ids', $data)) {
                $this->syncSuppliers($product, $data['supplier_ids'] ?? []);
            }

            if (array_key_exists('packs', $data)) {
                $this->syncPacks($product, $data['packs'] ?? []);
            }

            $this->audit->log(
                action: 'product.created',
                auditable: $product,
                metadata: [
                    'sku' => $product->sku,
                    'supplier_ids' => $data['supplier_ids'] ?? [],
                    'packs' => count($data['packs'] ?? []),
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $product->load(['category', 'suppliers', 'packs']);
        });
    }

    public function generateSku(Business $business): string
    {
        $prefix = 'SKU-';
        $next = 1;

        $existing = Product::query()
            ->forBusiness($business)
            ->where('sku', 'like', $prefix.'%')
            ->pluck('sku');

        foreach ($existing as $sku) {
            if (preg_match('/^SKU-(\d+)$/', (string) $sku, $matches) === 1) {
                $next = max($next, ((int) $matches[1]) + 1);
            }
        }

        do {
            $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (
            Product::query()
                ->forBusiness($business)
                ->where('sku', $candidate)
                ->exists()
        );

        return $candidate;
    }

    /**
     * @param  array{
     *     name?: string,
     *     sku?: string,
     *     barcode?: string|null,
     *     category_id?: int|null,
     *     description?: string|null,
     *     base_unit_name?: string,
     *     cost_price?: int,
     *     selling_price?: int,
     *     min_selling_price?: int,
     *     is_negotiable?: bool,
     *     reorder_level?: int,
     *     is_active?: bool,
     *     supplier_ids?: list<int>,
     *     packs?: list<array{
     *         id?: int|null,
     *         name: string,
     *         units_per_pack: int,
     *         barcode?: string|null,
     *         selling_price?: int|null,
     *         is_active?: bool,
     *     }>,
     * }  $data
     */
    public function update(Product $product, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($product, $data, $actor): Product {
            $supplierIds = $data['supplier_ids'] ?? null;
            $packs = $data['packs'] ?? null;
            unset($data['supplier_ids'], $data['packs']);

            if (array_key_exists('base_unit_name', $data)) {
                $data['base_unit_name'] = $this->normalizeBaseUnitName($data['base_unit_name']);
            }

            $product->update($data);

            if (is_array($supplierIds)) {
                $this->syncSuppliers($product, $supplierIds);
            }

            if (is_array($packs)) {
                $this->syncPacks($product, $packs);
            }

            $this->audit->log(
                action: 'product.updated',
                auditable: $product,
                metadata: [
                    ...$data,
                    'supplier_ids' => $supplierIds,
                    'packs' => is_array($packs) ? count($packs) : null,
                ],
                actor: $actor,
                businessId: $product->business_id,
            );

            return $product->refresh()->load(['category', 'suppliers', 'packs']);
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
            $this->sync->track(ProductSupplier::class, ['product_id' => $product->id], function () use ($product): void {
                $product->suppliers()->detach();
            });

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

        $this->sync->track(ProductSupplier::class, ['product_id' => $product->id], function () use ($product, $sync): void {
            $product->suppliers()->sync($sync);
        });
    }

    /**
     * @param  list<array{
     *     id?: int|null,
     *     name: string,
     *     units_per_pack: int,
     *     barcode?: string|null,
     *     selling_price?: int|null,
     *     is_active?: bool,
     * }>  $packs
     */
    public function syncPacks(Product $product, array $packs): void
    {
        $keepIds = [];
        $seenBarcodes = [];

        foreach ($packs as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $units = (int) ($row['units_per_pack'] ?? 0);
            $barcode = isset($row['barcode']) && trim((string) $row['barcode']) !== ''
                ? trim((string) $row['barcode'])
                : null;

            if ($name === '') {
                throw ValidationException::withMessages([
                    "packs.{$index}.name" => 'Pack name is required.',
                ]);
            }

            if ($units < 2) {
                throw ValidationException::withMessages([
                    "packs.{$index}.units_per_pack" => 'A pack must contain at least 2 base units.',
                ]);
            }

            if ($barcode !== null) {
                $barcodeKey = strtolower($barcode);
                if (isset($seenBarcodes[$barcodeKey])) {
                    throw ValidationException::withMessages([
                        "packs.{$index}.barcode" => 'Duplicate pack barcode in this product.',
                    ]);
                }
                $seenBarcodes[$barcodeKey] = true;

                $productBarcodeTaken = Product::query()
                    ->forBusiness($product->business_id)
                    ->where('barcode', $barcode)
                    ->exists();

                $packBarcodeTaken = ProductPack::query()
                    ->forBusiness($product->business_id)
                    ->where('barcode', $barcode)
                    ->when(
                        isset($row['id']) && $row['id'],
                        fn ($q) => $q->whereKeyNot((int) $row['id']),
                    )
                    ->exists();

                if ($productBarcodeTaken || $packBarcodeTaken) {
                    throw ValidationException::withMessages([
                        "packs.{$index}.barcode" => 'This barcode is already in use.',
                    ]);
                }
            }

            $payload = [
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'name' => $name,
                'units_per_pack' => $units,
                'barcode' => $barcode,
                'selling_price' => array_key_exists('selling_price', $row) ? $row['selling_price'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $existingId = isset($row['id']) ? (int) $row['id'] : null;
            if ($existingId) {
                $pack = ProductPack::query()
                    ->forBusiness($product->business_id)
                    ->where('product_id', $product->id)
                    ->whereKey($existingId)
                    ->first();

                if ($pack === null) {
                    throw ValidationException::withMessages([
                        "packs.{$index}.id" => 'Invalid pack.',
                    ]);
                }

                $pack->update($payload);
                $keepIds[] = $pack->id;
            } else {
                $pack = ProductPack::query()->create($payload);
                $keepIds[] = $pack->id;
            }
        }

        ProductPack::query()
            ->where('product_id', $product->id)
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->delete();
    }

    protected function normalizeBaseUnitName(?string $name): string
    {
        $normalized = trim((string) $name);

        return $normalized !== '' ? mb_substr($normalized, 0, 40) : 'piece';
    }
}
