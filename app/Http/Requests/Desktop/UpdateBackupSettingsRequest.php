<?php

namespace App\Http\Requests\Desktop;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && ($this->user()?->can('update', $business) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auto_backup' => ['required', 'boolean'],
            'auto_backup_hour' => ['required', 'integer', 'min:0', 'max:23'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('auto_backup')) {
            $this->merge([
                'auto_backup' => filter_var($this->input('auto_backup'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }
}
