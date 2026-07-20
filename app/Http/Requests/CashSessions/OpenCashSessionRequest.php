<?php

namespace App\Http\Requests\CashSessions;

use App\Enums\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends FormRequest
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
            'opening_float' => ['required', 'integer', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
