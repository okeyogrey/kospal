<?php

namespace App\Http\Requests\Productivity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateErrorReportingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'error_reporting_enabled' => ['required', 'boolean'],
            'include_diagnostics' => ['required', 'boolean'],
        ];
    }
}
