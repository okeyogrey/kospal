<?php

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('prevents cross-business branch access via route binding', function () {
    ['owner' => $ownerA, 'business' => $businessA] = $this->createBusinessWithOwner();
    ['business' => $businessB, 'branch' => $branchB] = $this->createBusinessWithOwner();

    $this->actingAs($ownerA)
        ->patch(route('branches.update', $branchB), [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect(Branch::query()->find($branchB->id)?->name)->not->toBe('Hijacked')
        ->and(Branch::query()->forBusiness($businessA)->whereKey($branchB->id)->exists())->toBeFalse()
        ->and(Branch::query()->forBusiness($businessB)->whereKey($branchB->id)->exists())->toBeTrue();
});

it('prevents cross-business membership updates', function () {
    ['owner' => $ownerA] = $this->createBusinessWithOwner();
    ['business' => $businessB] = $this->createBusinessWithOwner();
    $memberB = $this->addMember($businessB, BusinessRole::Cashier);

    $membershipB = BusinessMembership::query()
        ->where('business_id', $businessB->id)
        ->where('user_id', $memberB->id)
        ->firstOrFail();

    $this->actingAs($ownerA)
        ->patch(route('staff.memberships.update', $membershipB), [
            'role' => 'manager',
            'branch_ids' => [],
        ])
        ->assertNotFound();
});

it('scopes staff listings to the current business only', function () {
    ['owner' => $ownerA, 'business' => $businessA, 'branch' => $branchA] = $this->createBusinessWithOwner();
    ['business' => $businessB] = $this->createBusinessWithOwner([
        'name' => 'Other Biz',
    ]);

    $this->addMember($businessA, BusinessRole::Manager);
    $this->addMember($businessB, BusinessRole::Manager);

    $this->actingAs($ownerA)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/index')
            ->has('memberships', 2)
            ->where('memberships.0.user.email', $ownerA->email)
        );
});

it('denies cashier access to branch administration', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($cashier)
        ->get(route('branches.index'))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->post(route('branches.store'), [
            'name' => 'Unauthorized Branch',
        ])
        ->assertForbidden();
});

it('keeps platform super admin authorization separate from business roles', function () {
    $admin = User::factory()->platformSuperAdmin()->create();
    ['business' => $business] = $this->createBusinessWithOwner();

    expect($admin->can('manageSubscription', $business))->toBeFalse()
        ->and($admin->can('viewAny', PlatformSetting::class))->toBeTrue();
});
