<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    PlatformSetting::setPaymentInstructions([
        'title' => 'Pay offline',
        'body' => 'Use M-Pesa then submit your code.',
        'bank_name' => 'Test Bank',
        'account_name' => 'KOSPAL',
        'account_number' => '111',
        'mobile_money' => 'Paybill 999',
        'support_note' => null,
    ]);
});

it('lets owners submit a subscription request with a transaction code', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'subscription_status' => SubscriptionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('subscription.requests.store'), [
            'requested_plan' => 'pro',
            'transaction_code' => 'MPESA-ABC-123',
            'notes' => 'Paid via till',
        ])
        ->assertRedirect();

    $request = SubscriptionRequest::query()->forBusiness($business)->first();

    expect($request)->not->toBeNull()
        ->and($request->requested_plan)->toBe(Plan::Pro)
        ->and($request->transaction_code)->toBe('MPESA-ABC-123')
        ->and($request->status)->toBe(SubscriptionRequestStatus::Pending)
        ->and(AuditLog::query()->where('action', 'subscription.requested')->exists())->toBeTrue();
});

it('requires a transaction code when submitting a request', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->post(route('subscription.requests.store'), [
            'requested_plan' => 'pro',
            'transaction_code' => '',
        ])
        ->assertSessionHasErrors('transaction_code');
});

it('blocks non-owners from submitting subscription requests', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $manager = $this->addMember($business, BusinessRole::Manager, branchIds: [$branch->id]);

    $this->actingAs($manager)
        ->post(route('subscription.requests.store'), [
            'requested_plan' => 'pro',
            'transaction_code' => 'MPESA-ABC-123',
        ])
        ->assertForbidden();
});

it('prevents a second pending request for the same business', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    SubscriptionRequest::factory()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $owner->id,
        'status' => SubscriptionRequestStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('subscription.requests.store'), [
            'requested_plan' => 'enterprise',
            'transaction_code' => 'BANK-999',
        ])
        ->assertSessionHasErrors('requested_plan');
});

it('shows payment instructions and plans on the owner subscription page', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('subscription/index')
            ->where('payment_instructions.title', 'Pay offline')
            ->has('plans', 3)
            ->where('business.allows_write_access', true)
        );
});
