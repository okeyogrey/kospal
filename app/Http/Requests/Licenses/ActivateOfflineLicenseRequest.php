<?php

namespace App\Http\Requests\Licenses;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class ActivateOfflineLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && ($this->user()?->can('manageSubscription', $business) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activation_code' => ['required', 'string', 'min:20', 'max:4000'],
        ];
    }
}
