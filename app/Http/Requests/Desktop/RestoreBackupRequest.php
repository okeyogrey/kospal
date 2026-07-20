<?php

namespace App\Http\Requests\Desktop;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
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
            'backup_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+\.sqlite$/'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'You must confirm that restoring will replace the current database.',
        ];
    }
}
