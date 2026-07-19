<?php

namespace App\Policies;

use App\Models\StaffShift;
use App\Models\User;
use App\Policies\Concerns\AuthorizesShifts;
use App\Support\Tenancy\TenantContext;

class StaffShiftPolicy
{
    use AuthorizesShifts;

    public function __construct(
        protected TenantContext $tenant,
    ) {}

    protected function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function viewAny(User $user): bool
    {
        return $this->canMonitorShifts();
    }

    public function view(User $user, StaffShift $shift): bool
    {
        if (! $this->sameBusiness($shift->business_id)) {
            return false;
        }

        if ($this->canMonitorShifts()) {
            return true;
        }

        return $this->canClockShifts() && $shift->user_id === $user->id;
    }

    public function clockIn(User $user): bool
    {
        return $this->canClockShifts();
    }

    public function clockOut(User $user, StaffShift $shift): bool
    {
        return $this->canClockShifts()
            && $this->sameBusiness($shift->business_id)
            && $shift->user_id === $user->id
            && $shift->status->isOpen();
    }

    public function forceClose(User $user, StaffShift $shift): bool
    {
        return $this->canMonitorShifts()
            && $this->sameBusiness($shift->business_id)
            && $shift->status->isOpen();
    }
}
