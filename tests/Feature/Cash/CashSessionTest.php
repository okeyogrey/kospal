<?php

use App\Enums\BusinessRole;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StaffShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('opens a cash drawer with opening float after clocking in', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)
        ->post(route('cash-sessions.open'), [
            'opening_float' => 50000,
            'opening_notes' => 'Morning float',
        ])
        ->assertRedirect();

    $session = CashSession::query()->forBusiness($business)->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(CashSessionStatus::Open)
        ->and($session->opening_float)->toBe(50000)
        ->and($session->user_id)->toBe($cashier->id);
});

it('calculates expected cash from opening float and cash sales', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)->post(route('cash-sessions.open'), [
        'opening_float' => 10000,
    ])->assertRedirect();

    $session = CashSession::query()->forBusiness($business)->firstOrFail();
    $shift = StaffShift::query()->forBusiness($business)->firstOrFail();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
        'selling_price' => 2500,
    ]);

    InventoryBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($cashier)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    $service = app(\App\Services\CashSessionService::class);

    expect($service->calculateExpectedCash($session->fresh()))->toBe(12500);

    $this->actingAs($cashier)
        ->post(route('cash-sessions.close', $session), [
            'counted_cash' => 12500,
            'closing_float_left' => 10000,
        ])
        ->assertRedirect();

    expect($session->fresh()->variance)->toBe(0)
        ->and($session->fresh()->status)->toBe(CashSessionStatus::Closed)
        ->and($session->fresh()->z_report_snapshot)->not->toBeNull();
});

it('requires manager approval and audits cash variance', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)->post(route('cash-sessions.open'), [
        'opening_float' => 10000,
    ])->assertRedirect();

    $session = CashSession::query()->forBusiness($business)->firstOrFail();

    $membership = $business->memberships()->where('user_id', $owner->id)->firstOrFail();
    $membership->update(['approval_pin' => bcrypt('1234')]);

    $this->actingAs($cashier)
        ->post(route('cash-sessions.close', $session), [
            'counted_cash' => 9000,
            'closing_float_left' => 8000,
            'variance_reason' => 'Missing change fund',
            'manager_approval' => ['pin' => '1234'],
        ])
        ->assertRedirect();

    expect($session->fresh()->variance)->toBe(-1000)
        ->and($session->fresh()->variance_reason)->toBe('Missing change fund');

    expect(AuditLog::query()
        ->forBusiness($business)
        ->where('action', 'cash_session.variance_recorded')
        ->exists())->toBeTrue();
});

it('blocks sales when cash drawer is not open', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
        'selling_price' => 1000,
    ]);

    InventoryBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($cashier)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('cash_session');
});

it('prevents clock out while cash drawer is still open', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)->post(route('cash-sessions.open'), [
        'opening_float' => 5000,
    ])->assertRedirect();

    $this->actingAs($cashier)
        ->post(route('shifts.clock-out'))
        ->assertSessionHasErrors('shift');
});

it('records paid-in and drop movements', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)->post(route('cash-sessions.open'), [
        'opening_float' => 10000,
    ])->assertRedirect();

    $session = CashSession::query()->forBusiness($business)->firstOrFail();

    $this->actingAs($cashier)
        ->post(route('cash-sessions.movements.store', $session), [
            'type' => 'paid_in',
            'amount' => 2000,
            'reason' => 'Petty cash',
        ])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->post(route('cash-sessions.movements.store', $session), [
            'type' => 'drop',
            'amount' => 5000,
            'reason' => 'Safe drop',
        ])
        ->assertRedirect();

    $service = app(\App\Services\CashSessionService::class);

    expect($service->calculateExpectedCash($session->fresh()))->toBe(7000);
});

it('links completed sales to the active cash session', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)->post(route('cash-sessions.open'), [
        'opening_float' => 10000,
    ])->assertRedirect();

    $session = CashSession::query()->forBusiness($business)->firstOrFail();
    $shift = StaffShift::query()->forBusiness($business)->firstOrFail();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
        'selling_price' => 1500,
    ]);

    InventoryBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    $this->actingAs($cashier)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    $sale = \App\Models\Sale::query()
        ->forBusiness($business)
        ->where('cash_session_id', $session->id)
        ->firstOrFail();

    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->staff_shift_id)->toBe($shift->id)
        ->and($sale->cash_session_id)->toBe($session->id);
});
