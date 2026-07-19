<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('enforces starter active branch limits', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Second Branch',
            'is_active' => true,
        ])
        ->assertStatus(422);

    expect(Branch::query()->forBusiness($business)->count())->toBe(1);
});

it('allows additional active branches on pro up to the plan cap', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Branch Two',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Branch Three',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Branch Four',
            'is_active' => true,
        ])
        ->assertStatus(422);

    expect(Branch::query()->forBusiness($business)->where('is_active', true)->count())->toBe(3);
});

it('enforces staff seat limits including pending invitations', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    // Owner already uses 1 seat. Starter allows 5 total.
    foreach (range(1, 4) as $i) {
        $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    }

    $this->actingAs($owner)
        ->post(route('staff.invitations.store'), [
            'email' => 'overflow@example.com',
            'role' => 'cashier',
            'branch_ids' => [$branch->id],
        ])
        ->assertStatus(422);

    expect(Invitation::query()->forBusiness($business)->count())->toBe(0);
});

it('does not trust a browser-supplied plan when creating branches', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Forged Upgrade Branch',
            'is_active' => true,
            'plan' => 'enterprise',
            'business_id' => 999,
        ])
        ->assertStatus(422);

    expect($business->fresh()->plan)->toBe(Plan::Starter)
        ->and(Branch::query()->forBusiness($business)->count())->toBe(1);
});
