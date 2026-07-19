<?php

namespace App\Http\Requests\Brands;

use App\Models\Brand;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return $this->user()?->can('update', $brand) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');
        $businessId = app(TenantContext::class)->businessId();
        $categoryId = $this->input('category_id', $brand->category_id);

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('brands', 'name')
                    ->where('business_id', $businessId)
                    ->where('category_id', $categoryId)
                    ->ignore($brand->id),
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
