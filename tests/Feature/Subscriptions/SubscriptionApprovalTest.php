<?php

use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    config(['deployment.mode' => 'web']);
});

it('lets platform admins approve a request and activate the plan', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
        'subscription_status' => SubscriptionStatus::Pending,
    ]);

    $request = SubscriptionRequest::factory()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $owner->id,
        'requested_plan' => Plan::Pro,
        'current_plan' => Plan::Starter,
        'transaction_code' => 'TXN-APPROVE-1',
    ]);

    $admin = User::factory()->platformSuperAdmin()->create();

    $this->actingAs($admin)
        ->post(route('platform.subscription-requests.approve', $request), [
            'plan' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth()->toDateString(),
            'reviewer_notes' => 'Payment verified',
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(SubscriptionRequestStatus::Approved)
        ->and($request->fresh()->reviewer_notes)->toBe('Payment verified')
        ->and($business->fresh()->plan)->toBe(Plan::Pro)
        ->and($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and(AuditLog::query()->where('action', 'subscription.approved')->exists())->toBeTrue();
});

it('lets platform admins reject a request with reviewer notes', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $request = SubscriptionRequest::factory()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $owner->id,
    ]);

    $admin = User::factory()->platformSuperAdmin()->create();

    $this->actingAs($admin)
        ->post(route('platform.subscription-requests.reject', $request), [
            'reviewer_notes' => 'Code not found',
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(SubscriptionRequestStatus::Rejected)
        ->and($business->fresh()->plan)->toBe(Plan::Starter)
        ->and(AuditLog::query()->where('action', 'subscription.rejected')->exists())->toBeTrue();
});

it('forbids owners from reviewing subscription requests', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $request = SubscriptionRequest::factory()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('platform.subscription-requests.approve', $request), [
            'plan' => 'pro',
            'subscription_status' => 'active',
        ])
        ->assertForbidden();
});

it('lets platform admins update a business plan and status directly', function () {
    ['business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $admin = User::factory()->platformSuperAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('platform.businesses.subscription.update', $business), [
            'plan' => 'enterprise',
            'subscription_status' => 'suspended',
            'subscription_ends_at' => now()->addWeek()->toDateString(),
            'reviewer_notes' => 'Suspended for review',
        ])
        ->assertRedirect();

    expect($business->fresh()->plan)->toBe(Plan::Enterprise)
        ->and($business->fresh()->subscription_status)->toBe(SubscriptionStatus::Suspended)
        ->and(AuditLog::query()->where('action', 'subscription.updated')->exists())->toBeTrue();
});

it('lets platform admins update payment instructions', function () {
    $admin = User::factory()->platformSuperAdmin()->create();

    $this->actingAs($admin)
        ->put(route('platform.payment-instructions.update'), [
            'title' => 'Bank transfer',
            'body' => 'Pay to the KOSPAL account then submit your ref.',
            'bank_name' => 'KCB',
            'account_name' => 'KOSPAL',
            'account_number' => '445566',
            'mobile_money' => null,
            'support_note' => 'Reply with business name',
        ])
        ->assertRedirect();

    expect(PlatformSetting::paymentInstructions()['bank_name'])->toBe('KCB')
        ->and(AuditLog::query()->where('action', 'platform.payment_instructions.updated')->exists())->toBeTrue();
});

it('forbids regular users from editing payment instructions', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->put(route('platform.payment-instructions.update'), [
            'title' => 'Hacked',
            'body' => 'Nope',
        ])
        ->assertForbidden();
});
