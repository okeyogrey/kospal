<?php

namespace App\Http\Requests\GoodsReceived;

use App\Models\GoodsReceivedNote;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.product_pack_id' => [
                'nullable',
                'integer',
                Rule::exists('product_packs', 'id')->where('business_id', $businessId)->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.purchase_order_item_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_order_items', 'id')->where('business_id', $businessId),
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

        $currency = app(TenantContext::class)->business()?->currency
            ?? throw ValidationException::withMessages([
                'currency' => 'Business currency is required to save money amounts.',
            ]);

        return [
            ...$data,
            'items' => collect($data['items'] ?? [])->map(function (array $item, int $index) use ($currency): array {
                try {
                    $item['unit_cost'] = Money::toMinor($item['unit_cost'], $currency);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit_cost" => 'Enter a valid amount.',
                    ]);
                }

                return $item;
            })->values()->all(),
        ];
    }
}
