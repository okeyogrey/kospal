<?php

namespace App\Http\Requests\Onboarding;

use App\Support\Deployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isPlatformSuperAdmin() && Deployment::isWeb()) {
            return false;
        }

        return ! $user->memberships()->where('is_active', true)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', Rule::in(array_keys(config('kospal.countries')))],
            'currency' => ['required', 'string', Rule::in(array_keys(config('kospal.currencies')))],
            'timezone' => ['nullable', 'timezone:all'],
            'default_locale' => ['nullable', 'string', Rule::in(array_keys(config('kospal.locales')))],
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_city' => ['nullable', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
