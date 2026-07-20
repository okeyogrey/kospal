<?php

use App\Enums\LicenseActivationMode;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\User;
use App\Services\BusinessOnboardingService;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    config([
        'deployment.license.machine_id_path' => storage_path('framework/testing/machine_id_'.uniqid('', true)),
        'deployment.license.secret' => 'testing-license-secret',
        'deployment.license.server_url' => null,
        'deployment.license.trial_days' => 30,
    ]);
});

it('starts a 30-day trial on desktop onboarding', function () {
    $owner = User::factory()->create();

    $result = app(BusinessOnboardingService::class)->onboard($owner, [
        'name' => 'Trial Shop',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Main',
    ]);

    $business = $result['business']->fresh();

    expect($business->subscription_status)->toBe(SubscriptionStatus::Trial)
        ->and($business->license_activation_mode)->toBe(LicenseActivationMode::Trial)
        ->and($business->subscription_ends_at)->not->toBeNull()
        ->and($business->subscription_ends_at->isFuture())->toBeTrue()
        ->and($business->licensed_machine_id)->not->toBeEmpty();
});

it('allows writes during an active trial', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDays(20),
    ]);

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Drinks',
        ])
        ->assertRedirect();

    expect(Category::query()->where('name', 'Drinks')->exists())->toBeTrue();
});

it('expires a past-due trial into read-only mode', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Blocked',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired)
        ->and(Category::query()->where('name', 'Blocked')->exists())->toBeFalse();
});

it('activates an online license key and unlocks the edition features', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDays(5),
    ]);

    $key = app(LicenseService::class)->issueKey([
        'edition' => 'pro',
        'days' => 365,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $key,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $business->refresh();

    expect($business->plan)->toBe(Plan::Pro)
        ->and($business->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($business->license_activation_mode)->toBe(LicenseActivationMode::Online)
        ->and($business->licensed_machine_id)->toBe(app(LicenseService::class)->machineId());
});

it('activates an offline machine-bound code', function () {
    $licenses = app(LicenseService::class);
    $machineId = $licenses->machineId();

    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $code = $licenses->issueKey([
        'edition' => 'enterprise',
        'days' => 180,
        'machine_id' => $machineId,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-offline'), [
            'activation_code' => $code,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($business->fresh()->plan)->toBe(Plan::Enterprise)
        ->and($business->fresh()->license_activation_mode)->toBe(LicenseActivationMode::Offline)
        ->and($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('rejects offline codes issued for another machine', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $code = app(LicenseService::class)->issueKey([
        'edition' => 'pro',
        'days' => 30,
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-DDDD',
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-offline'), [
            'activation_code' => $code,
        ])
        ->assertSessionHasErrors('activation_code');
});

it('redirects the legacy subscription page to settings license on desktop', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->get(route('subscription.index'))
        ->assertRedirect(route('license.edit'));
});

it('renders settings license for owners', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDays(12),
    ]);

    $this->actingAs($owner)
        ->get(route('license.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/license')
            ->has('license.machine_id')
            ->where('license.status', 'trial')
            ->has('plans')
        );
});

it('continues enforcing plan feature limits after activation', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
        'subscription_status' => SubscriptionStatus::Active,
        'license_activation_mode' => LicenseActivationMode::Online,
        'subscription_ends_at' => now()->addYear(),
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Second Branch',
            'is_active' => true,
        ])
        ->assertStatus(422);
});

it('blocks SaaS subscription writes when deployment mode is web', function () {
    config(['deployment.mode' => 'web']);

    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Blocked',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Category::query()->where('name', 'Blocked')->exists())->toBeFalse();
});
