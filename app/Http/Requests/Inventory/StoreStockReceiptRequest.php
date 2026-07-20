<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryBalance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branchId = (int) $this->input('branch_id');
        $branch = app(TenantContext::class)->business()
            ?->branches()
            ->whereKey($branchId)
            ->first();

        if ($branch === null) {
            return false;
        }

        return $this->user()?->can('receiveStock', [InventoryBalance::class, $branch]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
