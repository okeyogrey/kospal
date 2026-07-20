<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Enums\StaffShiftStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\StaffShift;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Time\OperatingHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffShiftService
{
    public function __construct(
        protected AuditLogger $audit,
        protected ResolvesTenant $resolver,
        protected CashSessionService $cashSessions,
    ) {}

    /**
     * @return array{shift: StaffShift, outside_hours: bool}
     */
    public function clockIn(
        Business $business,
        Branch $branch,
        User $actor,
        BusinessRole $role,
    ): array {
        if (! $role->canClockShifts()) {
            throw ValidationException::withMessages([
                'shift' => 'Your role cannot clock in for shifts.',
            ]);
        }

        $membership = $business->memberships()
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'shift' => 'You are not an active member of this business.',
            ]);
        }

        if (! $this->resolver->allowedBranches($actor, $membership, $business)->contains('id', $branch->id)) {
            throw ValidationException::withMessages([
                'shift' => 'You do not have access to this branch.',
            ]);
        }

        return DB::transaction(function () use ($business, $branch, $actor, $role) {
            $existing = StaffShift::query()
                ->forBusiness($business)
                ->where('user_id', $actor->id)
                ->where('status', StaffShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'shift' => $existing->branch_id === $branch->id
                        ? 'You already have an open shift on this branch. Clock out first.'
                        : 'You already have an open shift at another branch. Clock out first.',
                ]);
            }

            $outsideHours = OperatingHours::isOutsideHours($business, $branch);

            $shift = StaffShift::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'user_id' => $actor->id,
                'role_at_clock_in' => $role,
                'status' => StaffShiftStatus::Open,
                'clocked_in_at' => now(),
                'notes' => $outsideHours
                    ? 'Clocked in outside configured daytime hours.'
                    : null,
            ]);

            $this->audit->log(
                action: 'shift.clocked_in',
                auditable: $shift,
                metadata: [
                    'branch_id' => $branch->id,
                    'outside_hours' => $outsideHours,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return [
                'shift' => $shift->load(['branch:id,name', 'user:id,name']),
                'outside_hours' => $outsideHours,
            ];
        });
    }

    public function clockOut(StaffShift $shift, User $actor): StaffShift
    {
        return DB::transaction(function () use ($shift, $actor) {
            $locked = StaffShift::query()
                ->whereKey($shift->id)
                ->with('cashSession')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->user_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'shift' => 'You can only clock out of your own shift.',
                ]);
            }

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'This shift is already closed.',
                ]);
            }

            $openCashSession = $locked->cashSession;

            if ($openCashSession !== null && $openCashSession->status->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'Close and reconcile the cash drawer before clocking out.',
                ]);
            }

            $locked->update([
                'status' => StaffShiftStatus::Closed,
                'clocked_out_at' => now(),
                'clocked_out_by' => $actor->id,
            ]);

            $this->audit->log(
                action: 'shift.clocked_out',
                auditable: $locked,
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->refresh()->load(['branch:id,name', 'user:id,name']);
        });
    }

    public function forceClose(StaffShift $shift, User $actor, string $reason): StaffShift
    {
        return DB::transaction(function () use ($shift, $actor, $reason) {
            $locked = StaffShift::query()
                ->whereKey($shift->id)
                ->with('cashSession')
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'This shift is already closed.',
                ]);
            }

            $openCashSession = $locked->cashSession;

            if ($openCashSession !== null && $openCashSession->status->isOpen()) {
                $this->cashSessions->forceClose($openCashSession, $actor, $reason);
            }

            $locked->update([
                'status' => StaffShiftStatus::ForceClosed,
                'clocked_out_at' => now(),
                'clocked_out_by' => $actor->id,
                'close_reason' => $reason,
            ]);

            $this->audit->log(
                action: 'shift.force_closed',
                auditable: $locked,
                metadata: ['reason' => $reason],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->refresh()->load(['branch:id,name', 'user:id,name,email', 'closedBy:id,name']);
        });
    }

    public function openShiftFor(Business $business, User $user): ?StaffShift
    {
        return StaffShift::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('status', StaffShiftStatus::Open)
            ->with(['branch:id,name'])
            ->first();
    }

    public function hasOpenShiftOnBranch(Business $business, User $user, Branch $branch): bool
    {
        return StaffShift::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('branch_id', $branch->id)
            ->where('status', StaffShiftStatus::Open)
            ->exists();
    }
}
