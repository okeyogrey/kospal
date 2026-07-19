<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): ExpenseCategory
    {
        $category = ExpenseCategory::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log(
            action: 'expense_category.created',
            auditable: $category,
            actor: $actor,
            businessId: $business->id,
        );

        return $category;
    }

    /**
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(ExpenseCategory $category, array $data, User $actor): ExpenseCategory
    {
        $category->update($data);

        $this->audit->log(
            action: 'expense_category.updated',
            auditable: $category,
            metadata: $data,
            actor: $actor,
            businessId: $category->business_id,
        );

        return $category->refresh();
    }

    public function delete(ExpenseCategory $category, User $actor): void
    {
        if ($category->expenses()->exists()) {
            throw ValidationException::withMessages([
                'expense_category' => 'Reassign or delete expenses before removing this category.',
            ]);
        }

        $businessId = $category->business_id;
        $payload = [
            'expense_category_id' => $category->id,
            'name' => $category->name,
        ];
        $category->delete();

        $this->audit->log(
            action: 'expense_category.deleted',
            metadata: $payload,
            actor: $actor,
            businessId: $businessId,
        );
    }
}
