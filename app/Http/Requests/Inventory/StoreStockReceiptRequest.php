<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryBalance;
use App\Models\ProductPack;
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
            'product_pack_id' => [
                'nullable',
                'integer',
                Rule::exists('product_packs', 'id')
                    ->where('business_id', $businessId)
                    ->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $packId = $this->input('product_pack_id');
            $productId = (int) $this->input('product_id');

            if ($packId === null || $packId === '' || $productId < 1) {
                return;
            }

            $exists = ProductPack::query()
                ->whereKey((int) $packId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('product_pack_id', 'Selected pack does not belong to this product.');
            }
        });
    }
}
