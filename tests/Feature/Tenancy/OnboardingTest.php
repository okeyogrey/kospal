<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects users without a business to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

it('lets a new owner create a business and first branch', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'name' => 'Kisumu Retail',
            'country' => 'KE',
            'currency' => 'KES',
            'branch_name' => 'Main Counter',
            'branch_city' => 'Kisumu',
        ])
        ->assertRedirect(route('dashboard'));

    $business = Business::query()->where('name', 'Kisumu Retail')->first();

    expect($business)->not->toBeNull()
        ->and($business->plan)->toBe(Plan::Starter)
        ->and($business->subscription_status)->toBe(SubscriptionStatus::Pending)
        ->and($business->owner_user_id)->toBe($user->id)
        ->and($business->branches)->toHaveCount(1)
        ->and($user->fresh()->memberships()->first()?->role)->toBe(BusinessRole::Owner)
        ->and(AuditLog::query()->where('action', 'business.onboarded')->exists())->toBeTrue();
});

it('prevents a user who already belongs to a business from onboarding again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => 'First Biz',
        'country' => 'KE',
        'currency' => 'KES',
        'branch_name' => 'Branch A',
    ])->assertRedirect(route('dashboard'));

    $this->actingAs($user->fresh())->post(route('onboarding.store'), [
        'name' => 'Second Biz',
        'country' => 'BI',
        'currency' => 'BIF',
        'branch_name' => 'Branch B',
    ])->assertForbidden();

    expect(Business::query()->count())->toBe(1);
});
