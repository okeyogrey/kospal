<?php

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Enums\Plan;
use App\Models\BusinessMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('sends invitation email even when the invitee has no account yet', function () {
    Notification::fake();

    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $this->actingAs($owner)
        ->post(route('staff.invitations.store'), [
            'email' => 'newperson@example.com',
            'role' => 'cashier',
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();

    Notification::assertSentOnDemand(
        BusinessInvitationNotification::class,
        function (BusinessInvitationNotification $notification, array $channels, object $notifiable) use ($business) {
            return in_array('mail', $channels, true)
                && $notifiable->routes['mail'] === 'newperson@example.com'
                && $notification->invitation->business_id === $business->id;
        },
    );
});

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

it('lets a new invitee create an account and join from the invitation', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $this->actingAs($owner)->post(route('staff.invitations.store'), [
        'email' => 'freshhire@example.com',
        'role' => 'cashier',
        'branch_ids' => [$branch->id],
    ]);

    $invitation = Invitation::query()->forBusiness($business)->firstOrFail();

    auth()->logout();

    $this->post(route('invitations.register', $invitation->token), [
        'name' => 'Fresh Hire',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'freshhire@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Fresh Hire')
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    $membership = BusinessMembership::query()
        ->where('business_id', $business->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership?->role)->toBe(BusinessRole::Cashier)
        ->and($user->fresh()->branches()->pluck('branches.id')->all())->toContain($branch->id);
});

it('rejects accepting when the user is already a member', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $invitation = Invitation::factory()->create([
        'business_id' => $business->id,
        'email' => $owner->email,
        'role' => BusinessRole::Manager,
        'invited_by_user_id' => $owner->id,
        'status' => InvitationStatus::Pending,
        'expires_at' => now()->addDay(),
        'branch_ids' => [$branch->id],
    ]);

    $this->actingAs($owner)
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
