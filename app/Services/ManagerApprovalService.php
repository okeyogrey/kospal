<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ManagerApprovalService
{
    /**
     * Resolve an approver via manager PIN, or login + password (legacy).
     *
     * @param  array{pin?: string|null, login?: string|null, password?: string|null}|null  $approval
     */
    public function resolve(
        Business $business,
        User $actor,
        bool $required,
        ?array $approval,
    ): ?User {
        if (! $required) {
            return null;
        }

        $actorRole = $this->roleFor($business, $actor);

        if ($actorRole !== null && $actorRole->canApplySaleDiscount()) {
            return $actor;
        }

        $pin = trim((string) ($approval['pin'] ?? ''));

        if ($pin !== '') {
            return $this->resolveByPin($business, $pin);
        }

        $login = trim((string) ($approval['login'] ?? ''));
        $password = (string) ($approval['password'] ?? '');

        if ($login === '' || $password === '') {
            throw ValidationException::withMessages([
                'manager_approval' => 'Manager approval is required. Enter a manager PIN, or an owner/manager login and password.',
            ]);
        }

        $approver = User::query()
            ->where(function ($query) use ($login): void {
                $query->where('email', $login)
                    ->orWhere('phone', $login);
            })
            ->first();

        if ($approver === null || ! Hash::check($password, $approver->password)) {
            throw ValidationException::withMessages([
                'manager_approval.password' => 'Invalid manager credentials.',
            ]);
        }

        $role = $this->roleFor($business, $approver);

        if ($role === null || ! $role->canApplySaleDiscount()) {
            throw ValidationException::withMessages([
                'manager_approval.login' => 'Only an active owner or manager of this business may approve.',
            ]);
        }

        return $approver;
    }

    protected function resolveByPin(Business $business, string $pin): User
    {
        if (! preg_match('/^\d{4,8}$/', $pin)) {
            throw ValidationException::withMessages([
                'manager_approval.pin' => 'Manager PIN must be 4 to 8 digits.',
            ]);
        }

        $memberships = BusinessMembership::query()
            ->forBusiness($business)
            ->where('is_active', true)
            ->whereIn('role', [BusinessRole::Owner, BusinessRole::Manager])
            ->whereNotNull('approval_pin')
            ->with('user')
            ->get();

        foreach ($memberships as $membership) {
            if ($membership->approval_pin !== null && Hash::check($pin, $membership->approval_pin)) {
                $user = $membership->user;

                if ($user !== null) {
                    return $user;
                }
            }
        }

        throw ValidationException::withMessages([
            'manager_approval.pin' => 'Invalid manager PIN.',
        ]);
    }

    protected function roleFor(Business $business, User $user): ?BusinessRole
    {
        $membership = BusinessMembership::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $membership?->role;
    }
}
