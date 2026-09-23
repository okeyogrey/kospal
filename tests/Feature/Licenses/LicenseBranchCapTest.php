<?php

use App\Enums\LicenseActivationMode;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
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

it('requires a keep selection when a smaller license cannot cover open branches', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDay(),
    ]);

    Branch::factory()->count(2)->create(['business_id' => $business->id]);

    $key = app(LicenseService::class)->issueKey([
        'edition' => 'starter',
        'days' => 365,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $key,
        ])
        ->assertSessionHasErrors('keep_branch_ids');

    expect($business->fresh()->plan)->toBe(Plan::Enterprise)
        ->and(Branch::query()->forBusiness($business)->where('is_active', true)->count())->toBe(3);
});

it('pauses extra branches when activating starter and keeps their records', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $kept] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
        'subscription_status' => SubscriptionStatus::Expired,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $extras = Branch::factory()->count(2)->create(['business_id' => $business->id]);

    $key = app(LicenseService::class)->issueKey([
        'edition' => 'starter',
        'days' => 365,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $key,
            'keep_branch_ids' => [$kept->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($business->fresh()->plan)->toBe(Plan::Starter)
        ->and($kept->fresh()->is_active)->toBeTrue()
        ->and($kept->fresh()->plan_paused_max_branches)->toBeNull()
        ->and(Branch::query()->forBusiness($business)->count())->toBe(3);

    foreach ($extras as $extra) {
        expect($extra->fresh()->is_active)->toBeFalse()
            ->and($extra->fresh()->plan_paused_max_branches)->toBe(1);
    }

    expect(AuditLog::query()->where('action', 'branch.plan_paused')->exists())->toBeTrue();
});

it('does not allow swapping a paused branch back in on the same plan', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $kept] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDays(5),
    ]);

    $second = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Second Shop',
    ]);
    $third = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Third Shop',
    ]);
    $fourth = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Fourth Shop',
    ]);

    $key = app(LicenseService::class)->issueKey([
        'edition' => 'pro',
        'days' => 365,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $key,
            'keep_branch_ids' => [$kept->id, $second->id, $third->id],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(route('branches.update', $third), [
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(route('branches.update', $fourth), [
            'is_active' => true,
        ])
        ->assertSessionHasErrors('is_active');

    expect($fourth->fresh()->is_active)->toBeFalse()
        ->and($fourth->fresh()->plan_paused_max_branches)->toBe(3);
});

it('allows reopening a paused branch after upgrading to a larger plan', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $kept] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
        'subscription_status' => SubscriptionStatus::Trial,
        'license_activation_mode' => LicenseActivationMode::Trial,
        'subscription_ends_at' => now()->addDays(5),
    ]);

    $paused = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Trial Extra',
    ]);

    $starterKey = app(LicenseService::class)->issueKey([
        'edition' => 'starter',
        'days' => 30,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $starterKey,
            'keep_branch_ids' => [$kept->id],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(route('branches.update', $paused), [
            'is_active' => true,
        ])
        ->assertSessionHasErrors('is_active');

    $enterpriseKey = app(LicenseService::class)->issueKey([
        'edition' => 'enterprise',
        'days' => 365,
    ]);

    $this->actingAs($owner)
        ->post(route('license.activate-online'), [
            'license_key' => $enterpriseKey,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(route('branches.update', $paused), [
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($paused->fresh()->is_active)->toBeTrue()
        ->and($paused->fresh()->plan_paused_max_branches)->toBeNull();
});
