<?php

namespace App\Http\Requests\Staff;

use App\Enums\BusinessRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('membership')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actorRole = app(TenantContext::class)->role();

        $allowedRoles = $actorRole === BusinessRole::Manager
            ? array_map(fn (BusinessRole $role) => $role->value, BusinessRole::assignableByManager())
            : array_values(array_filter(
                BusinessRole::values(),
                fn (string $role) => $role !== BusinessRole::Owner->value,
            ));

        return [
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ];
    }
}
