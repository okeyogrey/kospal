<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Branch;
use App\Models\Sale;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HoldSaleRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        $tenant = app(TenantContext::class);
        $business = $tenant->business();
        $branchId = $this->integer('branch_id');

        if ($business === null || $branchId < 1) {
            return $this->user()?->can('create', Sale::class) ?? false;
        }

        $branch = Branch::query()
            ->forBusiness($business)
            ->whereKey($branchId)
            ->first();

        if ($branch === null) {
            return false;
        }

        return $this->user()?->can('hold', [Sale::class, $branch]) ?? false;
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
                Rule::exists('branches', 'id')->where('business_id', $businessId)->where('is_active', true),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('business_id', $businessId)->where('is_active', true),
            ],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'held_label' => ['nullable', 'string', 'max:120'],
            'held_sale_id' => [
                'nullable',
                'integer',
                Rule::exists('sales', 'id')->where('business_id', $businessId)->where('status', 'held'),
            ],
            'manager_approval' => ['nullable', 'array'],
            'manager_approval.pin' => ['nullable', 'string', 'max:8'],
            'manager_approval.login' => ['nullable', 'string', 'max:255'],
            'manager_approval.password' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId)->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.list_unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

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

        $money = $this->moneyFieldsToMinor(['discount_amount']);

        return [
            ...$data,
            'branch_id' => (int) $data['branch_id'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'discount_amount' => $money['discount_amount'] ?? 0,
            'held_sale_id' => isset($data['held_sale_id']) ? (int) $data['held_sale_id'] : null,
            'manager_approval' => $data['manager_approval'] ?? null,
            'items' => collect($data['items'] ?? [])->map(function (array $item) use ($currency): array {
                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                        ? Money::toMinor($item['unit_price'], $currency)
                        : null,
                    'list_unit_price' => array_key_exists('list_unit_price', $item) && $item['list_unit_price'] !== null && $item['list_unit_price'] !== ''
                        ? Money::toMinor($item['list_unit_price'], $currency)
                        : null,
                ];
            })->values()->all(),
        ];
    }
}
