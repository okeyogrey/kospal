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
     * Resolve an approver via a unique 6-digit manager PIN.
     *
     * @param  array{pin?: string|null}|null  $approval
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

        if ($this->actorCanSelfApprove($business, $actor)) {
            return $actor;
        }

        $pin = trim((string) ($approval['pin'] ?? ''));

        if ($pin === '') {
            throw ValidationException::withMessages([
                'manager_approval.pin' => 'Manager approval is required. Enter a 6-digit manager PIN.',
            ]);
        }

        return $this->resolveByPin($business, $pin);
    }

    protected function actorCanSelfApprove(Business $business, User $actor): bool
    {
        $actorRole = $this->roleFor($business, $actor);

        if ($actorRole !== null && $actorRole->canApplySaleDiscount()) {
            return true;
        }

        return $actorRole === BusinessRole::Cashier
            && $business->cashiers_can_approve_price_overrides;
    }

    protected function resolveByPin(Business $business, string $pin): User
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw ValidationException::withMessages([
                'manager_approval.pin' => 'Manager PIN must be exactly 6 digits.',
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
