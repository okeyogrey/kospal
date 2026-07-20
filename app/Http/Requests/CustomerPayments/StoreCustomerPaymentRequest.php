<?php

namespace App\Http\Requests\CustomerPayments;

use App\Enums\PaymentMethod;
use App\Models\CustomerPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerPayment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('business_id', $businessId),
            ],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sale_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('sales', 'id')->where('business_id', $businessId),
            ],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
