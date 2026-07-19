<?php

use App\Enums\BusinessRole;
use App\Enums\OperatingMode;
use App\Enums\StaffShiftStatus;
use App\Models\StaffShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('allows cashiers to clock in and out on their branch', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($cashier)
        ->post(route('shifts.clock-in'))
        ->assertRedirect();

    $shift = StaffShift::query()->forBusiness($business)->first();

    expect($shift)->not->toBeNull()
        ->and($shift->status)->toBe(StaffShiftStatus::Open)
        ->and($shift->user_id)->toBe($cashier->id)
        ->and($shift->branch_id)->toBe($branch->id);

    $this->actingAs($cashier)
        ->post(route('shifts.clock-out'))
        ->assertRedirect();

    expect($shift->fresh()->status)->toBe(StaffShiftStatus::Closed)
        ->and($shift->fresh()->clocked_out_at)->not->toBeNull();
});

it('rejects a second open shift until the first is closed', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $second = \App\Models\Branch::factory()->create(['business_id' => $business->id]);
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id, $second->id]);

    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $cashier->forceFill(['current_branch_id' => $second->id])->save();
    app(\App\Support\Tenancy\ResolvesTenant::class)->resolve($cashier->fresh());

    $this->actingAs($cashier)
        ->post(route('shifts.clock-in'))
        ->assertSessionHasErrors('shift');
});

it('lets owners force-close forgotten shifts', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();
    $shift = StaffShift::query()->forBusiness($business)->firstOrFail();

    $this->actingAs($owner)
        ->post(route('shifts.force-close', $shift), [
            'close_reason' => 'Forgot to clock out',
        ])
        ->assertRedirect();

    expect($shift->fresh()->status)->toBe(StaffShiftStatus::ForceClosed)
        ->and($shift->fresh()->close_reason)->toBe('Forgot to clock out');
});

it('blocks POS sales when cashier is not clocked in on the active branch', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $product = \App\Models\Product::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
        'selling_price' => 1000,
    ]);
    \App\Models\InventoryBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($cashier)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'client_request_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('shift');
});

it('allows POS sales after clock-in', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $product = \App\Models\Product::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
        'selling_price' => 1000,
        'cost_price' => 500,
    ]);
    \App\Models\InventoryBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($cashier)->post(route('shifts.clock-in'))->assertRedirect();

    $this->actingAs($cashier)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'client_request_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    expect(\App\Models\Sale::query()->forBusiness($business)->count())->toBe(1);
});

it('still allows clock-in outside daytime hours with a soft note', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'operating_mode' => OperatingMode::Daytime,
        'opens_at' => '09:00:00',
        'closes_at' => '17:00:00',
        'timezone' => 'Africa/Nairobi',
    ]);
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->travelTo(now('Africa/Nairobi')->setTime(22, 0)->utc());

    $this->actingAs($cashier)
        ->post(route('shifts.clock-in'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $shift = StaffShift::query()->forBusiness($business)->firstOrFail();

    expect($shift->status)->toBe(StaffShiftStatus::Open)
        ->and($shift->notes)->toContain('outside');
});

it('isolates shift monitoring across tenants', function () {
    ['owner' => $ownerA, 'business' => $businessA, 'branch' => $branchA] = $this->createBusinessWithOwner();
    ['business' => $businessB, 'branch' => $branchB] = $this->createBusinessWithOwner(['name' => 'Other Biz']);
    $cashierB = $this->addMember($businessB, BusinessRole::Cashier, branchIds: [$branchB->id]);

    $this->actingAs($cashierB)->post(route('shifts.clock-in'));
    $shiftB = StaffShift::query()->forBusiness($businessB)->firstOrFail();

    $this->actingAs($ownerA)
        ->get(route('shifts.show', $shiftB))
        ->assertNotFound();

    expect(StaffShift::query()->forBusiness($businessA)->count())->toBe(0);
});
