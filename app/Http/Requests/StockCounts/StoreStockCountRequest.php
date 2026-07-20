<?php

namespace App\Http\Requests\StockCounts;

use App\Models\StockCount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();
        if ($business === null) {
            return false;
        }

        $branch = $business->branches()->whereKey((int) $this->input('branch_id'))->first();

        return $branch !== null
            && ($this->user()?->can('create', [StockCount::class, $branch]) ?? false);
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
        ];
    }
}
