<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Branch;
use App\Models\Sale;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'payment_method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.method' => ['required_with:payments', 'string', Rule::in(PaymentMethod::values())],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.tendered_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.change_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'change_given' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_request_id' => ['required', 'string', 'uuid', 'max:64'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasPayments = is_array($this->input('payments')) && $this->input('payments') !== [];
            $hasMethod = filled($this->input('payment_method'));

            if (! $hasPayments && ! $hasMethod) {
                $validator->errors()->add('payments', 'Add at least one payment method.');
            }

            $discount = $this->input('discount_amount');
            $needsDiscountAuth = $discount !== null && $discount !== '' && (float) $discount > 0;
            $canSelfApprove = $this->user()?->can('applyDiscount', Sale::class) ?? false;
            $hasApproval = filled(data_get($this->input('manager_approval'), 'pin'))
                || (
                    filled(data_get($this->input('manager_approval'), 'login'))
                    && filled(data_get($this->input('manager_approval'), 'password'))
                );

            if ($needsDiscountAuth && ! $canSelfApprove && ! $hasApproval) {
                $validator->errors()->add(
                    'discount_amount',
                    'Manager approval is required to apply discounts.',
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

        $currency = app(TenantContext::class)->business()?->currency
            ?? throw ValidationException::withMessages([
                'currency' => 'Business currency is required to save money amounts.',
            ]);

        $money = $this->moneyFieldsToMinor(['discount_amount', 'cash_tendered', 'change_given']);

        $payments = collect($data['payments'] ?? [])->map(function (array $payment) use ($currency): array {
            return [
                'method' => $payment['method'],
                'amount' => Money::toMinor($payment['amount'], $currency),
                'reference' => $payment['reference'] ?? null,
                'tendered_amount' => isset($payment['tendered_amount']) && $payment['tendered_amount'] !== ''
                    ? Money::toMinor($payment['tendered_amount'], $currency)
                    : null,
                'change_amount' => isset($payment['change_amount']) && $payment['change_amount'] !== ''
                    ? Money::toMinor($payment['change_amount'], $currency)
                    : null,
            ];
        })->values()->all();

        return [
            ...$data,
            'branch_id' => (int) $data['branch_id'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'discount_amount' => $money['discount_amount'] ?? 0,
            'cash_tendered' => $money['cash_tendered'] ?? 0,
            'change_given' => $money['change_given'] ?? 0,
            'held_sale_id' => isset($data['held_sale_id']) ? (int) $data['held_sale_id'] : null,
            'payments' => $payments,
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
