<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('barcode') === '') {
            $this->merge(['barcode' => null]);
        }

        if ($this->input('category_id') === '' || $this->input('category_id') === '0') {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $businessId = app(TenantContext::class)->businessId();

        return [
            'name' => ['required', 'string', 'max:160'],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('products', 'sku')
                    ->where('business_id', $businessId)
                    ->ignore($product->id),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('products', 'barcode')
                    ->where('business_id', $businessId)
                    ->ignore($product->id),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $businessId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'supplier_ids' => ['sometimes', 'array'],
            'supplier_ids.*' => [
                'integer',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        $merged = [
            ...$data,
            ...$this->moneyFieldsToMinor(['cost_price', 'selling_price']),
            'reorder_level' => (int) ($data['reorder_level'] ?? 0),
        ];

        if (array_key_exists('supplier_ids', $data)) {
            $merged['supplier_ids'] = array_values(array_map('intval', $data['supplier_ids'] ?? []));
        }

        return $merged;
    }
}
