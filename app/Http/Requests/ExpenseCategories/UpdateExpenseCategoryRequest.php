<?php

namespace App\Http\Requests\ExpenseCategories;

use App\Models\ExpenseCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExpenseCategory $category */
        $category = $this->route('expenseCategory');

        return $this->user()?->can('update', $category) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();
        /** @var ExpenseCategory $category */
        $category = $this->route('expenseCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('expense_categories', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
