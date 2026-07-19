<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('receives stock through the inventory service with movement and audit', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create([
        'business_id' => $business->id,
        'reorder_level' => 5,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'note' => 'Delivery from supplier',
        ])
        ->assertRedirect();

    $balance = InventoryBalance::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->first();

    expect($balance?->quantity)->toBe(20)
        ->and(StockMovement::query()->where('type', StockMovementType::StockReceipt)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'inventory.stock_receipt')->exists())->toBeTrue();
});

it('allows receiving stock when quantity already exists', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 4,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(14);
});

it('records stock losses with required reason and note', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.adjustments.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => StockAdjustmentReason::Theft->value,
            'note' => 'Missing units after count',
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(7)
        ->and(StockMovement::query()->where('type', StockMovementType::Adjustment)->first()?->reason)
        ->toBe(StockAdjustmentReason::Theft)
        ->and(AuditLog::query()->where('action', 'inventory.adjustment')->exists())->toBeTrue();
});

it('never allows stock to become negative', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.adjustments.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reason' => StockAdjustmentReason::Loss->value,
            'note' => 'Attempted oversell',
        ])
        ->assertSessionHasErrors('quantity');

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(2)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('requires note for adjustments', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)
        ->post(route('inventory.adjustments.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reason' => StockAdjustmentReason::Other->value,
            'note' => '',
        ])
        ->assertSessionHasErrors('note');
});

it('lists low stock alerts and inventory values', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $low = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 1000,
        'reorder_level' => 10,
    ]);
    $ok = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 500,
        'reorder_level' => 2,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $low->id,
        'quantity' => 3,
    ]);
    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $ok->id,
        'quantity' => 8,
    ]);

    $this->actingAs($owner)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('summary.total_value_minor', (3 * 1000) + (8 * 500))
            ->has('balances.data', 2)
        );

    $this->actingAs($owner)
        ->get(route('inventory.low-stock'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/low-stock')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.product_id', $low->id)
        );
});

it('restricts inventory clerks to assigned branches', function () {
    ['business' => $business, 'branch' => $main] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $other = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Second Branch',
    ]);

    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$main->id]);
    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $other->id,
        'product_id' => $product->id,
        'quantity' => 12,
    ]);

    $this->actingAs($clerk)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('balances.data', 0));

    $this->actingAs($clerk)
        ->post(route('shifts.clock-in'))
        ->assertRedirect();

    $this->actingAs($clerk)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $other->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ])
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $main->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()
        ->where('branch_id', $main->id)
        ->where('product_id', $product->id)
        ->value('quantity'))->toBe(5);
});

it('shows product stock by branch and movement history', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    app(InventoryService::class)->receiveStock(
        $business,
        $branch,
        $product,
        15,
        $owner,
        'Seeded',
    );

    $this->actingAs($owner)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/show')
            ->has('product.stock_by_branch', 1)
            ->has('product.movements', 1)
            ->where('product.stock_by_branch.0.quantity', 15)
        );
});

it('keeps stock movements immutable', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);

    $movement = app(InventoryService::class)->receiveStock(
        $business,
        $branch,
        $product,
        4,
        $owner,
    );

    expect(fn () => $movement->update(['note' => 'tamper']))
        ->toThrow(RuntimeException::class);
});

it('applies sale movements without going negative via service', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create(['business_id' => $business->id]);
    $inventory = app(InventoryService::class);

    $inventory->receiveStock($business, $branch, $product, 5, $owner);

    $inventory->applyMovement(
        business: $business,
        branch: $branch,
        product: $product,
        type: StockMovementType::Sale,
        quantityDelta: -2,
        actor: $owner,
    );

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(3);

    expect(fn () => $inventory->applyMovement(
        business: $business,
        branch: $branch,
        product: $product,
        type: StockMovementType::Sale,
        quantityDelta: -10,
        actor: $owner,
    ))->toThrow(ValidationException::class);
});
