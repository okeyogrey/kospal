<?php

namespace App\Http\Requests\Staff;

use App\Enums\BusinessRole;
use App\Models\Invitation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ];
    }
}
