<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\ReferralCreditStatus;
use App\Enums\ReferralStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Referral;
use App\Models\ReferralCredit;
use App\Models\User;
use App\Notifications\ReferralInviteNotification;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    config([
        'deployment.mode' => 'web',
        'kospal.referral.server_url' => null,
    ]);
});

it('lets an owner generate a shareable invite that expires in three days', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->post(route('referrals.store'))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('referrals/index')
            ->where('credit_percent', 10)
            ->where('active_code.code', fn ($code) => str_starts_with((string) $code, 'KSP-'))
        );
});

it('blocks staff from creating referral invites', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $manager = $this->addMember($business, BusinessRole::Manager, branchIds: [$branch->id]);

    $this->actingAs($manager)
        ->post(route('referrals.store'))
        ->assertForbidden();
});

it('attaches a new owner who registers with an invite', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);

    $invitee = User::factory()->create();

    $this->actingAs($invitee)
        ->post(route('onboarding.store'), [
            'name' => 'Invited Shop',
            'country' => 'KE',
            'currency' => 'KES',
            'branch_name' => 'Main',
            'referral_code' => $code->code,
        ])
        ->assertRedirect(route('dashboard'));

    $referral = Referral::query()->first();

    expect($referral)->not->toBeNull()
        ->and($referral->status)->toBe(ReferralStatus::Pending)
        ->and($referral->qualifies_at->toDateString())->toBe(now()->addWeek()->toDateString());
});

it('rejects self-referrals and a second referred-by', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($business, $owner);

    $this->actingAs($owner)
        ->post(route('onboarding.store'), [
            'name' => 'Same Owner Shop',
            'country' => 'KE',
            'currency' => 'KES',
            'branch_name' => 'Other',
            'referral_code' => $code->code,
        ])
        ->assertForbidden();

    $first = User::factory()->create();
    $this->actingAs($first)->post(route('onboarding.store'), [
        'name' => 'First Invitee',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'referral_code' => $code->code,
    ])->assertRedirect();

    $secondCode = app(ReferralService::class)->issueCode($business, $owner);
    $this->actingAs($first->fresh())->post(route('onboarding.store'), [
        'name' => 'Second Business',
        'country' => 'BI',
        'currency' => 'BIF',
        'branch_name' => 'Other',
        'referral_code' => $secondCode->code,
    ])->assertForbidden();
});

it('rejects expired invite codes', function () {
    ['owner' => $referrer, 'business' => $business] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($business, $referrer);
    $code->forceFill(['expires_at' => now()->subMinute()])->save();

    $invitee = User::factory()->create();

    $this->actingAs($invitee)
        ->post(route('onboarding.store'), [
            'name' => 'Late Shop',
            'country' => 'KE',
            'currency' => 'KES',
            'branch_name' => 'Main',
            'referral_code' => $code->code,
        ])
        ->assertSessionHasErrors('referral_code');
});

it('qualifies both sides after a week if the invitee stays active', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('onboarding.store'), [
        'name' => 'Active Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'referral_code' => $code->code,
    ])->assertRedirect();

    $this->travel(7)->days();
    expect(app(ReferralService::class)->qualifyDue())->toBe(1);

    expect(Referral::query()->first()->status)->toBe(ReferralStatus::Qualified)
        ->and(ReferralCredit::query()->count())->toBe(2)
        ->and(app(ReferralService::class)->availablePercent($referrerBusiness->fresh()))->toBe(10);
});

it('does not qualify a deactivated invitee', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('onboarding.store'), [
        'name' => 'Gone Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'referral_code' => $code->code,
    ])->assertRedirect();

    $invitee->fresh()->currentBusiness->forceFill(['is_active' => false])->save();

    $this->travel(7)->days();
    expect(app(ReferralService::class)->qualifyDue())->toBe(0)
        ->and(Referral::query()->first()->status)->toBe(ReferralStatus::Lapsed);
});

it('stacks credits and applies up to 100 percent on the first payment', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
        'currency' => 'KES',
        'subscription_status' => SubscriptionStatus::Trial,
    ]);

    foreach (range(1, 10) as $i) {
        $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);
        $invitee = User::factory()->create();
        $this->actingAs($invitee)->post(route('onboarding.store'), [
            'name' => "Shop {$i}",
            'country' => 'KE',
            'currency' => 'KES',
            'branch_name' => 'Main',
            'referral_code' => $code->code,
        ])->assertRedirect();
    }

    $this->travel(7)->days();
    expect(app(ReferralService::class)->qualifyDue())->toBe(10)
        ->and(app(ReferralService::class)->availablePercent($referrerBusiness->fresh()))->toBe(100);

    $applied = app(ReferralService::class)->applyToPayment($referrerBusiness->fresh());

    expect($applied['discount_percent'])->toBe(100)
        ->and($applied['due_minor'])->toBe(0)
        ->and($referrerBusiness->fresh()->first_paid_period_at)->not->toBeNull();
});

it('gives the invitee 10 percent off their first payment only', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('onboarding.store'), [
        'name' => 'Invitee Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'referral_code' => $code->code,
    ])->assertRedirect();

    $this->travel(7)->days();
    app(ReferralService::class)->qualifyDue();

    $inviteeBusiness = $invitee->fresh()->currentBusiness;
    $first = app(ReferralService::class)->applyToPayment($inviteeBusiness);

    expect($first['discount_percent'])->toBe(10);

    ReferralCredit::query()->create([
        'referral_id' => Referral::query()->first()->id,
        'referral_account_id' => $inviteeBusiness->referralAccount->id,
        'side' => 'referrer',
        'percent' => 10,
        'remaining_percent' => 10,
        'status' => ReferralCreditStatus::Available,
        'first_payment_only' => true,
        'available_at' => now(),
    ]);

    $second = app(ReferralService::class)->applyToPayment($inviteeBusiness->fresh());

    expect($second['discount_percent'])->toBe(0);
});

it('lets platform admins view and void referrals', function () {
    ['owner' => $referrer, 'business' => $referrerBusiness] = $this->createBusinessWithOwner();
    $code = app(ReferralService::class)->issueCode($referrerBusiness, $referrer);
    $invitee = User::factory()->create();
    $this->actingAs($invitee)->post(route('onboarding.store'), [
        'name' => 'Review Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'referral_code' => $code->code,
    ])->assertRedirect();

    $this->travel(7)->days();
    app(ReferralService::class)->qualifyDue();

    $admin = User::factory()->platformSuperAdmin()->create();
    $referral = Referral::query()->first();

    $this->actingAs($admin)
        ->get(route('platform.referrals.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('platform.referrals.void', $referral), ['reason' => 'Fraud'])
        ->assertRedirect();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Voided)
        ->and(ReferralCredit::query()->where('status', ReferralCreditStatus::Voided)->count())->toBe(2);
});

it('starts a 60-day trial by default', function () {
    config(['deployment.mode' => 'desktop']);

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('onboarding.store'), [
        'name' => 'Trial Length Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
        'trial_edition' => 'starter',
    ])->assertRedirect();

    $ends = $owner->fresh()->currentBusiness->subscription_ends_at;

    expect($ends->isSameDay(now()->addDays(60)))->toBeTrue();
});

it('emails an invite without inviting the owner themselves', function () {
    Notification::fake();

    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->post(route('referrals.email'), ['email' => $owner->email])
        ->assertSessionHasErrors('email');

    $this->actingAs($owner)
        ->post(route('referrals.email'), ['email' => 'friend@example.com'])
        ->assertRedirect();

    Notification::assertSentOnDemand(ReferralInviteNotification::class);
});
