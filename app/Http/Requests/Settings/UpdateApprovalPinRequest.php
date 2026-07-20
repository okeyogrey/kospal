<?php

namespace App\Http\Requests\Settings;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateApprovalPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = app(TenantContext::class)->role();

        return $role !== null && $role->canApplySaleDiscount();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
            'pin_confirmation' => ['nullable', 'same:pin'],
            'current_password' => ['required', 'current_password'],
            'clear_pin' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clear = (bool) $this->boolean('clear_pin');
            $pin = $this->input('pin');

            if (! $clear && ($pin === null || $pin === '')) {
                $validator->errors()->add('pin', 'Enter a 4–8 digit PIN, or choose to clear it.');
            }
        });
    }
}
