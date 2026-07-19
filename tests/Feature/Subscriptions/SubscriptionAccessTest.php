<?php

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('keeps data visible but blocks writes when subscription is pending', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('products.index'))
        ->assertOk();

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Drinks',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Category::query()->count())->toBe(0);
});

it('blocks operational writes when subscription is expired or suspended', function (SubscriptionStatus $status) {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => $status,
        'subscription_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->post(route('branches.store'), [
            'name' => 'Blocked Branch',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Branch::query()->where('name', 'Blocked Branch')->exists())->toBeFalse();
})->with([
    SubscriptionStatus::Expired,
    SubscriptionStatus::Suspended,
]);

it('auto-expires an active subscription past its end date and blocks writes', function () {
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

    expect($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired);
});

it('still allows subscription request submission while restricted', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Expired,
    ]);

    $this->actingAs($owner)
        ->post(route('subscription.requests.store'), [
            'requested_plan' => 'pro',
            'transaction_code' => 'RENEW-001',
        ])
        ->assertRedirect()
        ->assertSessionMissing('error');
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
});
