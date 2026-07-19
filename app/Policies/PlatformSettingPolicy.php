<?php

namespace App\Policies;

use App\Models\PlatformSetting;
use App\Models\User;

class PlatformSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function update(User $user, ?PlatformSetting $setting = null): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
