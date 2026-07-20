<?php

namespace App\Http\Requests\SupplierPayments;

use App\Enums\PaymentMethod;
use App\Models\SupplierPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierPayment::class) ?? false;
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
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.supplier_invoice_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('supplier_invoices', 'id')->where('business_id', $businessId),
            ],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
