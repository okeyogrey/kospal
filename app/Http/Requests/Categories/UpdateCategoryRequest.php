<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $this->user()?->can('update', $category) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');
        $businessId = app(TenantContext::class)->businessId();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
