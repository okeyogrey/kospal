<?php

namespace App\Http\Requests\SupplierInvoices;

use App\Models\SupplierInvoice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierInvoice::class) ?? false;
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
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'goods_received_note_id' => [
                'nullable',
                'integer',
                Rule::exists('goods_received_notes', 'id')->where('business_id', $businessId),
            ],
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where('business_id', $businessId),
            ],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_total' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }
}
