<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();
        if ($business === null) {
            return false;
        }

        $branch = $business->branches()->whereKey((int) $this->input('branch_id'))->first();

        return $branch !== null
            && ($this->user()?->can('create', [PurchaseOrder::class, $branch]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }
}
