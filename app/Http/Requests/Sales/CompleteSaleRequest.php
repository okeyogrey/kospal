<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Branch;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompleteSaleRequest extends FormRequest
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

        return $this->user()?->can('create', [Sale::class, $branch]) ?? false;
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
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_request_id' => ['required', 'string', 'uuid', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId)->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $discount = $this->input('discount_amount');

            if ($discount === null || $discount === '' || (float) $discount <= 0) {
                return;
            }

            if (! ($this->user()?->can('applyDiscount', Sale::class) ?? false)) {
                $validator->errors()->add(
                    'discount_amount',
                    'Only owners and managers may apply discounts.',
                );
            }
        });
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        $money = $this->moneyFieldsToMinor(['discount_amount']);

        return [
            ...$data,
            'branch_id' => (int) $data['branch_id'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'discount_amount' => $money['discount_amount'] ?? 0,
            'items' => collect($data['items'] ?? [])->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])->values()->all(),
        ];
    }
}
