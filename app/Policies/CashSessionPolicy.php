<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\AuthorizesShifts;
use App\Support\Tenancy\TenantContext;

class CashSessionPolicy
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

    public function view(User $user, CashSession $session): bool
    {
        if (! $this->sameBusiness($session->business_id)) {
            return false;
        }

        if ($this->canMonitorShifts()) {
            return true;
        }

        return $this->canClockShifts() && $session->user_id === $user->id;
    }

    public function open(User $user): bool
    {
        return $this->canClockShifts();
    }

    public function recordMovement(User $user, CashSession $session): bool
    {
        return $this->canClockShifts()
            && $this->sameBusiness($session->business_id)
            && $session->user_id === $user->id
            && $session->status->isOpen();
    }

    public function close(User $user, CashSession $session): bool
    {
        return $this->canClockShifts()
            && $this->sameBusiness($session->business_id)
            && $session->user_id === $user->id
            && $session->status->isOpen();
    }

    public function forceClose(User $user, CashSession $session): bool
    {
        return $this->canMonitorShifts()
            && $this->sameBusiness($session->business_id)
            && $session->status->isOpen();
    }
}
