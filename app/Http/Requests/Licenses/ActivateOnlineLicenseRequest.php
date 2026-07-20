<?php

namespace App\Http\Requests\Licenses;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class ActivateOnlineLicenseRequest extends FormRequest
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
            'license_key' => ['required', 'string', 'min:20', 'max:4000'],
        ];
    }
}
