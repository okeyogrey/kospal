<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Product;
use App\Support\Money\Money;
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
            'base_unit_name' => ['nullable', 'string', 'max:40'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_selling_price' => ['nullable', 'numeric', 'min:0'],
            'is_negotiable' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'supplier_ids' => ['sometimes', 'array'],
            'supplier_ids.*' => [
                'integer',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
            'packs' => ['sometimes', 'array'],
            'packs.*.id' => [
                'nullable',
                'integer',
                Rule::exists('product_packs', 'id')->where(fn ($q) => $q
                    ->where('business_id', $businessId)
                    ->where('product_id', $product->id)),
            ],
            'packs.*.name' => ['required_with:packs', 'string', 'max:80'],
            'packs.*.units_per_pack' => ['required_with:packs', 'integer', 'min:2', 'max:1000000'],
            'packs.*.barcode' => ['nullable', 'string', 'max:80'],
            'packs.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'packs.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $selling = $this->input('selling_price');
            $min = $this->input('min_selling_price');

            if ($selling !== null && $min !== null && $min !== '' && (float) $min > (float) $selling) {
                $validator->errors()->add(
                    'min_selling_price',
                    'Minimum selling price cannot exceed the suggested selling price.',
                );
            }
        });
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

        $money = $this->moneyFieldsToMinor(['cost_price', 'selling_price', 'min_selling_price']);

        if (! array_key_exists('min_selling_price', $money) || $money['min_selling_price'] === null) {
            $money['min_selling_price'] = $money['cost_price'] ?? 0;
        }

        $merged = [
            ...$data,
            ...$money,
            'is_negotiable' => (bool) ($data['is_negotiable'] ?? true),
            'reorder_level' => (int) ($data['reorder_level'] ?? 0),
        ];

        if (array_key_exists('supplier_ids', $data)) {
            $merged['supplier_ids'] = array_values(array_map('intval', $data['supplier_ids'] ?? []));
        }

        if (array_key_exists('packs', $data)) {
            $merged['packs'] = $this->packsToMinor($data['packs'] ?? []);
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $packs
     * @return list<array<string, mixed>>
     */
    protected function packsToMinor(array $packs): array
    {
        $currency = app(TenantContext::class)->business()?->currency;
        if ($currency === null || $packs === []) {
            return $packs;
        }

        return array_map(function (array $pack) use ($currency): array {
            if (array_key_exists('selling_price', $pack) && $pack['selling_price'] !== null && $pack['selling_price'] !== '') {
                $pack['selling_price'] = Money::toMinor($pack['selling_price'], $currency);
            } else {
                $pack['selling_price'] = null;
            }

            if (($pack['barcode'] ?? '') === '') {
                $pack['barcode'] = null;
            }

            return $pack;
        }, $packs);
    }
}
