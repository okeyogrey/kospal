<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function void(User $user, Referral $referral): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
