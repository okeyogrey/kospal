<?php

namespace App\Http\Requests\Platform;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentInstructionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PlatformSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'bank_name' => ['nullable', 'string', 'max:160'],
            'account_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'mobile_money' => ['nullable', 'string', 'max:160'],
            'support_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
