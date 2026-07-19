<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function create(Business $business, array $data, User $actor): Category
    {
        $category = Category::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->audit->log(
            action: 'category.created',
            auditable: $category,
            actor: $actor,
            businessId: $business->id,
        );

        return $category;
    }

    /**
     * @param  array{name?: string, description?: string|null}  $data
     */
    public function update(Category $category, array $data, User $actor): Category
    {
        $category->update($data);

        $this->audit->log(
            action: 'category.updated',
            auditable: $category,
            metadata: $data,
            actor: $actor,
            businessId: $category->business_id,
        );

        return $category->refresh();
    }

    public function delete(Category $category, User $actor): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Move or delete products in this category before removing it.',
            ]);
        }

        $businessId = $category->business_id;
        $payload = ['category_id' => $category->id, 'name' => $category->name];
        $category->delete();

        $this->audit->log(
            action: 'category.deleted',
            metadata: $payload,
            actor: $actor,
            businessId: $businessId,
        );
    }
}
