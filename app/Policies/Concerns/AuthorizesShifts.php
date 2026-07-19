<?php

namespace App\Policies\Concerns;

use App\Support\Tenancy\TenantContext;

trait AuthorizesShifts
{
    abstract protected function tenant(): TenantContext;

    protected function canClockShifts(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canClockShifts();
    }

    protected function canMonitorShifts(): bool
    {
        $role = $this->tenant()->role();

        return $role !== null && $role->canMonitorShifts();
    }

    protected function sameBusiness(?int $businessId): bool
    {
        return $businessId !== null
            && $this->tenant()->businessId() !== null
            && $businessId === $this->tenant()->businessId();
    }
}
