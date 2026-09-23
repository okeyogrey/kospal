<?php

namespace App\Http\Requests\Settings;

use App\Enums\BusinessRole;
use App\Models\BusinessMembership;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
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
            'pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'pin_confirmation' => ['nullable', 'same:pin'],
            'current_password' => ['required', 'current_password'],
            'clear_pin' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clear = (bool) $this->boolean('clear_pin');
            $pin = (string) ($this->input('pin') ?? '');

            if (! $clear && $pin === '') {
                $validator->errors()->add('pin', 'Enter a 6-digit PIN, or choose to clear it.');

                return;
            }

            if ($clear || $pin === '' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $tenant = app(TenantContext::class);
            $business = $tenant->business();
            $membership = $tenant->membership();

            if ($business === null || $membership === null) {
                return;
            }

            $others = BusinessMembership::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->whereIn('role', [BusinessRole::Owner, BusinessRole::Manager])
                ->whereNotNull('approval_pin')
                ->whereKeyNot($membership->id)
                ->get(['id', 'approval_pin']);

            foreach ($others as $other) {
                if ($other->approval_pin !== null && Hash::check($pin, $other->approval_pin)) {
                    $validator->errors()->add(
                        'pin',
                        'This PIN is already used by another manager. Choose a different 6-digit PIN so approvals can identify who approved.',
                    );

                    return;
                }
            }
        });
    }
}
