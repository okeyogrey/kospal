<?php

namespace App\Http\Requests\Desktop;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
