<?php

namespace App\Http\Requests\Desktop;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrinterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && ($this->user()?->can('update', $business) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'printer_name' => ['nullable', 'string', 'max:120'],
            'receipt_width' => ['required', 'string', Rule::in(['58', '80'])],
        ];
    }
}
