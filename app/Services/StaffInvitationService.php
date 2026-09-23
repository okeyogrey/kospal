<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\BranchAssignment;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class StaffInvitationService
{
    public function __construct(
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $branchIds
     */
    public function invite(
        Business $business,
        User $inviter,
        string $email,
        BusinessRole $role,
        array $branchIds = [],
    ): Invitation {
        $this->limits->assertCanAddStaff($business);

        $email = strtolower(trim($email));

        if ($role === BusinessRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Owner role cannot be assigned via invitation.',
            ]);
        }

        if (BusinessMembership::query()
            ->forBusiness($business)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the business.',
            ]);
        }

        if (Invitation::query()
            ->forBusiness($business)
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email.',
            ]);
        }

        $validatedBranchIds = $this->validatedBranchIds($business, $role, $branchIds);

        $invitation = Invitation::query()->create([
            'business_id' => $business->id,
            'email' => $email,
            'role' => $role,
            'invited_by_user_id' => $inviter->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addHours((int) config('kospal.invitation_expires_hours', 72)),
            'branch_ids' => $validatedBranchIds,
        ]);

        Notification::route('mail', $email)
            ->notify(new BusinessInvitationNotification($invitation));

        $this->audit->log(
            action: 'staff.invited',
            auditable: $invitation,
            metadata: [
                'email' => $email,
                'role' => $role->value,
                'branch_ids' => $validatedBranchIds,
            ],
            actor: $inviter,
            businessId: $business->id,
        );

        return $invitation;
    }

    /**
     * Create an account for a new invitee and accept the invitation.
     *
     * @param  array{name: string, password: string}  $credentials
     */
    public function registerAndAccept(Invitation $invitation, array $credentials): BusinessMembership
    {
        if (! $invitation->isAcceptable()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'invitation' => 'An account already exists for this email. Log in to accept the invitation.',
            ]);
        }

        $business = $invitation->business()->firstOrFail();
        $this->limits->assertCanAddStaff($business);

        return DB::transaction(function () use ($invitation, $credentials, $business) {
            $user = User::query()->create([
                'name' => $credentials['name'],
                'email' => $invitation->email,
                'password' => $credentials['password'],
                'email_verified_at' => now(),
            ]);

            return $this->acceptForUser($invitation, $user, $business);
        });
    }

    public function accept(Invitation $invitation, User $user): BusinessMembership
    {
        if (! $invitation->isAcceptable()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation was sent to '.$invitation->email.'. Sign out and continue with that email.',
            ]);
        }

        $business = $invitation->business()->firstOrFail();
        $this->limits->assertCanAddStaff($business);

        if (BusinessMembership::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'invitation' => 'You are already a member of this business.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user, $business) {
            return $this->acceptForUser($invitation, $user, $business);
        });
    }

    protected function acceptForUser(Invitation $invitation, User $user, Business $business): BusinessMembership
    {
        $membership = BusinessMembership::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => $invitation->role,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->syncBranchAccess($business, $user, $invitation->role, $invitation->branch_ids ?? []);

        $invitation->update([
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
            'accepted_user_id' => $user->id,
        ]);

        if ($user->current_business_id === null) {
            $user->forceFill([
                'current_business_id' => $business->id,
                'current_branch_id' => ($invitation->branch_ids[0] ?? null),
            ])->save();
        }

        $this->audit->log(
            action: 'staff.invitation_accepted',
            auditable: $invitation,
            metadata: [
                'membership_id' => $membership->id,
                'role' => $invitation->role->value,
            ],
            actor: $user,
            businessId: $business->id,
        );

        return $membership;
    }

    public function revoke(Invitation $invitation, User $actor): void
    {
        $invitation->update(['status' => InvitationStatus::Revoked]);

        $this->audit->log(
            action: 'staff.invitation_revoked',
            auditable: $invitation,
            actor: $actor,
            businessId: $invitation->business_id,
        );
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function updateMembershipRole(
        BusinessMembership $membership,
        BusinessRole $role,
        array $branchIds,
        User $actor,
        ?int $negotiationFloorPercent = null,
    ): void {
        if ($membership->role === BusinessRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'The owner membership cannot be changed here.',
            ]);
        }

        if ($role === BusinessRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Owner role cannot be assigned this way.',
            ]);
        }

        $business = $membership->business()->firstOrFail();
        $validatedBranchIds = $this->validatedBranchIds($business, $role, $branchIds);

        $payload = ['role' => $role];

        if ($negotiationFloorPercent !== null) {
            $payload['negotiation_floor_percent'] = max(0, min(100, $negotiationFloorPercent));
        }

        $membership->update($payload);
        $this->syncBranchAccess($business, $membership->user, $role, $validatedBranchIds);

        $this->audit->log(
            action: 'staff.role_updated',
            auditable: $membership,
            metadata: [
                'role' => $role->value,
                'branch_ids' => $validatedBranchIds,
                'negotiation_floor_percent' => $membership->negotiation_floor_percent,
            ],
            actor: $actor,
            businessId: $business->id,
        );
    }

    public function deactivateMembership(BusinessMembership $membership, User $actor): void
    {
        if ($membership->role === BusinessRole::Owner) {
            throw ValidationException::withMessages([
                'membership' => 'The owner cannot be deactivated.',
            ]);
        }

        $membership->update(['is_active' => false]);

        DB::table('branch_user')
            ->where('business_id', $membership->business_id)
            ->where('user_id', $membership->user_id)
            ->delete();

        $this->audit->log(
            action: 'staff.deactivated',
            auditable: $membership,
            actor: $actor,
            businessId: $membership->business_id,
        );
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    protected function validatedBranchIds(Business $business, BusinessRole $role, array $branchIds): array
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));

        if ($role->requiresBranchAssignment() && $branchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'At least one branch assignment is required for this role.',
            ]);
        }

        if ($branchIds === []) {
            return [];
        }

        $count = Branch::query()
            ->forBusiness($business)
            ->whereIn('id', $branchIds)
            ->count();

        if ($count !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branch_ids' => 'One or more selected branches are invalid for this business.',
            ]);
        }

        return $branchIds;
    }

    /**
     * @param  list<int>  $branchIds
     */
    protected function syncBranchAccess(Business $business, User $user, BusinessRole $role, array $branchIds): void
    {
        $existing = BranchAssignment::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->get();

        // Managers may optionally be limited to specific branches. Empty means
        // unrestricted (all active branches) via ResolvesTenant::allowedBranches.
        // Cashiers and clerks always require at least one assignment.
        $keep = ($branchIds === [] && ! $role->requiresBranchAssignment())
            ? []
            : $branchIds;

        $keepIds = array_map(static fn (int|string $id): int => (int) $id, $keep);

        foreach ($existing as $assignment) {
            if (! in_array((int) $assignment->branch_id, $keepIds, true)) {
                $assignment->delete();
            }
        }

        foreach ($keepIds as $branchId) {
            $already = $existing->first(
                fn (BranchAssignment $assignment): bool => (int) $assignment->branch_id === $branchId,
            );

            if ($already !== null) {
                continue;
            }

            BranchAssignment::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'user_id' => $user->id,
            ]);
        }
    }
}
