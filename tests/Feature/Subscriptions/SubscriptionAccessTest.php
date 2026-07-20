<?php

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

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

it('continues enforcing plan limits on active accounts', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Second Branch',
            'is_active' => true,
        ])
        ->assertStatus(422);

    expect(Branch::query()->where('name', 'Second Branch')->exists())->toBeFalse();
});

it('auto-expires past-due SaaS subscriptions in web mode', function () {
    config(['deployment.mode' => 'web']);

    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Should Fail',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired)
        ->and(Category::query()->where('name', 'Should Fail')->exists())->toBeFalse();
});
