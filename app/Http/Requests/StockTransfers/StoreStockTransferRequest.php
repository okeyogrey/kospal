<?php

namespace App\Http\Requests\StockTransfers;

use App\Models\StockTransfer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        if ($business === null) {
            return false;
        }

        $source = $business->branches()->whereKey((int) $this->input('source_branch_id'))->first();
        $destination = $business->branches()->whereKey((int) $this->input('destination_branch_id'))->first();

        if ($source === null || $destination === null) {
            return false;
        }

        return $this->user()?->can('create', [StockTransfer::class, $source, $destination]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();

        return [
            'source_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'destination_branch_id' => [
                'required',
                'integer',
                'different:source_branch_id',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((int) $this->input('source_branch_id') === (int) $this->input('destination_branch_id')) {
                $validator->errors()->add(
                    'destination_branch_id',
                    'Source and destination branches must be different.',
                );
            }
        });
    }
}
