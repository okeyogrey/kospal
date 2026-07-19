<?php

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Enums\Plan;
use App\Models\BusinessMembership;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('invites and accepts staff with branch assignment', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $invitee = User::factory()->create([
        'email' => 'newhire@example.com',
    ]);

    $this->actingAs($owner)
        ->post(route('staff.invitations.store'), [
            'email' => 'newhire@example.com',
            'role' => 'cashier',
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();

    $invitation = Invitation::query()->forBusiness($business)->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->status)->toBe(InvitationStatus::Pending);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    $membership = BusinessMembership::query()
        ->where('business_id', $business->id)
        ->where('user_id', $invitee->id)
        ->first();

    expect($membership?->role)->toBe(BusinessRole::Cashier)
        ->and($invitee->fresh()->branches()->pluck('branches.id')->all())->toContain($branch->id)
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);
});

it('rejects invitation acceptance from a different email', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $this->actingAs($owner)->post(route('staff.invitations.store'), [
        'email' => 'intended@example.com',
        'role' => 'inventory_clerk',
        'branch_ids' => [$branch->id],
    ]);

    $invitation = Invitation::query()->forBusiness($business)->firstOrFail();
    $intruder = User::factory()->create(['email' => 'intruder@example.com']);

    $this->actingAs($intruder)
        ->post(route('invitations.accept', $invitation->token))
        ->assertSessionHasErrors('invitation');
});

it('persists manager branch assignments on staff update', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $manager = $this->addMember($business, BusinessRole::Manager);

    $this->actingAs($owner)
        ->patch(route('staff.memberships.update', $manager->memberships()->where('business_id', $business->id)->first()), [
            'role' => 'manager',
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();

    expect($manager->fresh()->branches()->pluck('branches.id')->all())->toBe([$branch->id]);
});
