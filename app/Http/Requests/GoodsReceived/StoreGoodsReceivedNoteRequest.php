<?php

namespace App\Http\Requests\GoodsReceived;

use App\Models\GoodsReceivedNote;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceivedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();
        if ($business === null) {
            return false;
        }

        $branch = $business->branches()->whereKey((int) $this->input('branch_id'))->first();

        return $branch !== null
            && ($this->user()?->can('create', [GoodsReceivedNote::class, $branch]) ?? false);
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
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where('business_id', $businessId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_invoice' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.purchase_order_item_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_order_items', 'id')->where('business_id', $businessId),
            ],
        ];
    }
}
