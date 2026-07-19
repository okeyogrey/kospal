<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class BrandService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, category_id: int, is_active?: bool}  $data
     */
    public function create(Business $business, array $data, User $actor): Brand
    {
        $this->assertSubSubcategory($business->id, $data['category_id']);

        $brand = Brand::query()->create([
            'business_id' => $business->id,
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log(
            action: 'brand.created',
            auditable: $brand,
            actor: $actor,
            businessId: $business->id,
        );

        return $brand;
    }

    /**
     * @param  array{name?: string, description?: string|null, category_id?: int, is_active?: bool}  $data
     */
    public function update(Brand $brand, array $data, User $actor): Brand
    {
        if (array_key_exists('category_id', $data)) {
            $this->assertSubSubcategory($brand->business_id, (int) $data['category_id']);
        }

        $brand->update($data);

        $this->audit->log(
            action: 'brand.updated',
            auditable: $brand,
            metadata: $data,
            actor: $actor,
            businessId: $brand->business_id,
        );

        return $brand->refresh();
    }

    public function delete(Brand $brand, User $actor): void
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages([
                'brand' => 'Remove or reassign products before deleting this brand.',
            ]);
        }

        $businessId = $brand->business_id;
        $payload = ['brand_id' => $brand->id, 'name' => $brand->name];
        $brand->delete();

        $this->audit->log(
            action: 'brand.deleted',
            metadata: $payload,
            actor: $actor,
            businessId: $businessId,
        );
    }

    protected function assertSubSubcategory(int $businessId, int $categoryId): void
    {
        $category = Category::query()
            ->forBusiness($businessId)
            ->whereKey($categoryId)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected sub-subcategory is invalid.',
            ]);
        }

        if (! $category->isSubSubcategory()) {
            throw ValidationException::withMessages([
                'category_id' => 'Brands must belong to a sub-subcategory.',
            ]);
        }
    }
}
