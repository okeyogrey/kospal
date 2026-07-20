<?php

namespace Tests\Support;

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;

trait CreatesBusinesses
{
    /**
     * @return array{owner: User, business: Business, branch: Branch, membership: BusinessMembership}
     */
    protected function createBusinessWithOwner(array $businessAttributes = [], ?User $owner = null): array
    {
        $owner ??= User::factory()->create();

        $business = Business::factory()->create([
            'owner_user_id' => $owner->id,
            'plan' => Plan::Starter,
            'subscription_status' => SubscriptionStatus::Active,
            ...$businessAttributes,
        ]);

        $branch = Branch::factory()->create([
            'business_id' => $business->id,
            'name' => 'Main Branch',
        ]);

        $membership = BusinessMembership::factory()->owner()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
        ]);

        $owner->forceFill([
            'current_business_id' => $business->id,
            'current_branch_id' => $branch->id,
        ])->save();

        return compact('owner', 'business', 'branch', 'membership');
    }

    protected function addMember(
        Business $business,
        BusinessRole $role,
        ?User $user = null,
        array $branchIds = [],
    ): User {
        $user ??= User::factory()->create();

        BusinessMembership::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        foreach ($branchIds as $branchId) {
            $user->branches()->attach($branchId, [
                'business_id' => $business->id,
            ]);
        }

        $user->forceFill([
            'current_business_id' => $business->id,
            'current_branch_id' => $branchIds[0] ?? null,
        ])->save();

        return $user;
    }

    protected function clockInAndOpenDrawer(User $user, int $openingFloat = 0): void
    {
        test()->actingAs($user)
            ->post(route('shifts.clock-in'))
            ->assertRedirect();

        test()->actingAs($user)
            ->post(route('cash-sessions.open'), [
                'opening_float' => $openingFloat,
            ])
            ->assertRedirect();
    }
}
