<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockAdjustmentReason;
use App\Models\InventoryBalance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
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

        return $this->user()?->can('adjust', [InventoryBalance::class, $branch]) ?? false;
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
            'direction' => ['required', 'string', Rule::in(['increase', 'decrease'])],
            'reason' => ['required', Rule::enum(StockAdjustmentReason::class)],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
