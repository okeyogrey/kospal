<?php

namespace App\Http\Requests\Business;

use App\Enums\OperatingMode;
use App\Models\Business;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && ($this->user()?->can('update', $business) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('opens_at') === '') {
            $this->merge(['opens_at' => null]);
        }

        if ($this->input('closes_at') === '') {
            $this->merge(['closes_at' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operating_mode' => ['required', 'string', Rule::in(OperatingMode::values())],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }
}
