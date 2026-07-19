<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Expense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        if ($business === null) {
            return false;
        }

        $branch = $business->branches()->whereKey((int) $this->input('branch_id'))->first();

        if ($branch === null) {
            return false;
        }

        return $this->user()?->can('create', [Expense::class, $branch]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->businessId();
        $maxKb = (int) config('kospal.attachments.max_kilobytes', 5120);
        $mimes = implode(',', config('kospal.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp']));

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('business_id', $businessId)
                    ->where('is_active', true),
            ],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payee' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => [
                'nullable',
                'file',
                'mimes:'.$mimes,
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:'.$maxKb,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        $money = $this->moneyFieldsToMinor(['amount']);

        return [
            ...$validated,
            ...$money,
        ];
    }
}
