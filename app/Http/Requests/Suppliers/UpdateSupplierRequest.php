<?php

namespace App\Http\Requests\Suppliers;

use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');

        return $this->user()?->can('update', $supplier) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');
        $businessId = app(TenantContext::class)->businessId();

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('suppliers', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($supplier->id),
            ],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => [
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
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

        $merged = [...$data];

        if (array_key_exists('product_ids', $data)) {
            $merged['product_ids'] = array_values(array_map('intval', $data['product_ids'] ?? []));
        }

        return $merged;
    }
}
