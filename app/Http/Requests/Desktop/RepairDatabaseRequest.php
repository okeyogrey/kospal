<?php

namespace App\Http\Requests\Desktop;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class RepairDatabaseRequest extends FormRequest
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
            'vacuum' => ['sometimes', 'boolean'],
            'reindex' => ['sometimes', 'boolean'],
            'backup_first' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['vacuum', 'reindex', 'backup_first'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                ]);
            }
        }
    }
}
