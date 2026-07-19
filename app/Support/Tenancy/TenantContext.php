<?php

namespace App\Support\Tenancy;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;

class TenantContext
{
    protected ?User $user = null;

    protected ?Business $business = null;

    protected ?BusinessMembership $membership = null;

    protected ?Branch $branch = null;

    public function set(
        ?User $user,
        ?Business $business = null,
        ?BusinessMembership $membership = null,
        ?Branch $branch = null,
    ): void {
        $this->user = $user;
        $this->business = $business;
        $this->membership = $membership;
        $this->branch = $branch;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->business = null;
        $this->membership = null;
        $this->branch = null;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function business(): ?Business
    {
        return $this->business;
    }

    public function businessId(): ?int
    {
        return $this->business?->id;
    }

    public function membership(): ?BusinessMembership
    {
        return $this->membership;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    public function role(): ?BusinessRole
    {
        return $this->membership?->role;
    }

    public function isPlatformSuperAdmin(): bool
    {
        return (bool) $this->user?->is_platform_super_admin;
    }

    public function hasBusiness(): bool
    {
        return $this->business !== null && $this->membership !== null;
    }
}
