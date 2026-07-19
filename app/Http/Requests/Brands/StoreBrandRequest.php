<?php

namespace App\Http\Requests\Brands;

use App\Models\Brand;
use App\Models\Category;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();
        $categoryId = $this->input('category_id');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('brands', 'name')
                    ->where('business_id', $businessId)
                    ->where('category_id', $categoryId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $businessId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
