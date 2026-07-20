<?php

namespace App\Http\Requests\CashSessions;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'integer', 'min:0'],
            'closing_float_left' => ['required', 'integer', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:2000'],
            'manager_approval' => ['nullable', 'array'],
            'manager_approval.pin' => ['nullable', 'string', 'max:8'],
            'manager_approval.login' => ['nullable', 'string', 'max:255'],
            'manager_approval.password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
