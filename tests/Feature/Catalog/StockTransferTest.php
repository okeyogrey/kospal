<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

/**
 * @return array{
 *     owner: User,
 *     business: Business,
 *     source: Branch,
 *     destination: Branch,
 *     product: Product
 * }
 */
function seededTransferContext(array $businessAttributes = []): array
{
    ['owner' => $owner, 'business' => $business, 'branch' => $source] = test()->createBusinessWithOwner([
        'plan' => Plan::Pro,
        ...$businessAttributes,
    ]);

    $destination = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Destination Branch',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'name' => 'Transferable Oil',
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $source->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    return compact('owner', 'business', 'source', 'destination', 'product');
}

function createDraftTransfer(
    $owner,
    Branch $source,
    Branch $destination,
    Product $product,
    int $quantity = 5,
    ?string $notes = 'Moving stock',
): StockTransfer {
    test()->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'notes' => $notes,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ])
        ->assertRedirect();

    return StockTransfer::query()->latest('id')->firstOrFail();
}

it('lists transfers with status and branch filters', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product);

    $this->actingAs($owner)
        ->get(route('stock-transfers.index', [
            'status' => 'draft',
            'source_branch_id' => $source->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transfers/index')
            ->has('transfers.data', 1)
            ->where('transfers.data.0.id', $transfer->id)
            ->where('filters.status', 'draft')
        );
});

it('creates a draft transfer without changing stock', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();

    $this->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'notes' => 'Weekly restock',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 7],
            ],
        ])
        ->assertRedirect();

    $transfer = StockTransfer::query()->first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->status)->toBe(StockTransferStatus::Draft)
        ->and($transfer->reference)->toStartWith('TRF-')
        ->and(StockTransferItem::query()->count())->toBe(1)
        ->and(InventoryBalance::query()->where('branch_id', $source->id)->value('quantity'))->toBe(20)
        ->and(InventoryBalance::query()->where('branch_id', $destination->id)->value('quantity'))->toBeNull()
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'stock_transfer.created')->exists())->toBeTrue();
});

it('dispatches a draft transfer and removes source stock atomically', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product, quantity: 8);

    $this->actingAs($owner)
        ->post(route('stock-transfers.dispatch', $transfer))
        ->assertRedirect();

    $transfer->refresh();

    expect($transfer->status)->toBe(StockTransferStatus::Dispatched)
        ->and($transfer->dispatched_at)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $source->id)->value('quantity'))->toBe(12)
        ->and(InventoryBalance::query()->where('branch_id', $destination->id)->value('quantity'))->toBeNull()
        ->and(StockMovement::query()->where('type', StockMovementType::TransferOut)->count())->toBe(1)
        ->and(StockMovement::query()->where('type', StockMovementType::TransferIn)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'stock_transfer.dispatched')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'inventory.transfer_out')->exists())->toBeTrue();

    $movement = StockMovement::query()->where('type', StockMovementType::TransferOut)->first();
    expect($movement?->reference_type)->toBe($transfer->getMorphClass())
        ->and($movement?->reference_id)->toBe($transfer->id)
        ->and($movement?->quantity_delta)->toBe(-8);
});

it('receives a dispatched transfer and adds destination stock atomically', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product, quantity: 6);

    $this->actingAs($owner)->post(route('stock-transfers.dispatch', $transfer))->assertRedirect();
    $this->actingAs($owner)->post(route('stock-transfers.receive', $transfer))->assertRedirect();

    $transfer->refresh();

    expect($transfer->status)->toBe(StockTransferStatus::Received)
        ->and($transfer->received_at)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $source->id)->value('quantity'))->toBe(14)
        ->and(InventoryBalance::query()->where('branch_id', $destination->id)->value('quantity'))->toBe(6)
        ->and(StockMovement::query()->where('type', StockMovementType::TransferIn)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'stock_transfer.received')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'inventory.transfer_in')->exists())->toBeTrue();
});

it('cancels a draft transfer without stock side effects', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product);

    $this->actingAs($owner)
        ->post(route('stock-transfers.cancel', $transfer))
        ->assertRedirect();

    $transfer->refresh();

    expect($transfer->status)->toBe(StockTransferStatus::Cancelled)
        ->and($transfer->cancelled_at)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $source->id)->value('quantity'))->toBe(20)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'stock_transfer.cancelled')->exists())->toBeTrue();
});

it('rejects same source and destination branches', function () {
    ['owner' => $owner, 'source' => $source, 'product' => $product] = seededTransferContext();

    $this->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $source->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertSessionHasErrors('destination_branch_id');
});

it('prevents transfer quantities above available stock on create', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();

    $this->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 50],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');

    expect(StockTransfer::query()->count())->toBe(0);
});

it('prevents dispatch when source stock is no longer sufficient', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product, quantity: 15);

    InventoryBalance::query()
        ->where('branch_id', $source->id)
        ->where('product_id', $product->id)
        ->update(['quantity' => 3]);

    $this->actingAs($owner)
        ->post(route('stock-transfers.dispatch', $transfer))
        ->assertSessionHasErrors('items.0.quantity');

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Draft)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(InventoryBalance::query()->where('branch_id', $source->id)->value('quantity'))->toBe(3);
});

it('rejects invalid status transitions', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product);

    $this->actingAs($owner)
        ->post(route('stock-transfers.receive', $transfer))
        ->assertForbidden();

    $this->actingAs($owner)->post(route('stock-transfers.dispatch', $transfer))->assertRedirect();

    $this->actingAs($owner)
        ->post(route('stock-transfers.cancel', $transfer))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('stock-transfers.dispatch', $transfer))
        ->assertForbidden();

    $this->actingAs($owner)->post(route('stock-transfers.receive', $transfer))->assertRedirect();

    $this->actingAs($owner)
        ->post(route('stock-transfers.receive', $transfer))
        ->assertForbidden();

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Received);
});

it('shows transfer detail with activity history', function () {
    ['owner' => $owner, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $transfer = createDraftTransfer($owner, $source, $destination, $product);

    $this->actingAs($owner)->post(route('stock-transfers.dispatch', $transfer))->assertRedirect();

    $this->actingAs($owner)
        ->get(route('stock-transfers.show', $transfer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transfers/show')
            ->where('transfer.id', $transfer->id)
            ->where('transfer.status', 'dispatched')
            ->has('transfer.items', 1)
            ->has('activity', 2)
            ->where('permissions.receive', true)
            ->where('permissions.cancel', false)
            ->has('transfer.movements', 1)
        );
});

it('denies stock transfers on starter plans', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $source] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $destination = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Second',
    ]);

    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $source->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($owner)
        ->get(route('stock-transfers.index'))
        ->assertStatus(422);

    $this->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertStatus(422);
});

it('allows cashiers neither list nor mutate transfers', function () {
    ['business' => $business, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$source->id]);

    $this->actingAs($cashier)
        ->get(route('stock-transfers.index'))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();
});

it('lets inventory clerks manage transfers only for assigned branches', function () {
    ['owner' => $owner, 'business' => $business, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();

    $third = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Third Branch',
    ]);

    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$source->id, $destination->id]);

    $this->actingAs($clerk)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])
        ->assertRedirect();

    $transfer = StockTransfer::query()->latest('id')->firstOrFail();

    $this->actingAs($clerk)
        ->post(route('stock-transfers.dispatch', $transfer))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $third->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect();

    $unassigned = StockTransfer::query()->latest('id')->firstOrFail();

    $this->actingAs($clerk)
        ->post(route('stock-transfers.dispatch', $unassigned))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $third->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();
});

it('allows managers to complete the full lifecycle', function () {
    ['business' => $business, 'source' => $source, 'destination' => $destination, 'product' => $product] = seededTransferContext();
    $manager = $this->addMember($business, BusinessRole::Manager);

    $this->actingAs($manager)
        ->post(route('stock-transfers.store'), [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'notes' => 'Manager move',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])
        ->assertRedirect();

    $transfer = StockTransfer::query()->latest('id')->firstOrFail();

    $this->actingAs($manager)->post(route('stock-transfers.dispatch', $transfer))->assertRedirect();
    $this->actingAs($manager)->post(route('stock-transfers.receive', $transfer))->assertRedirect();

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Received)
        ->and(InventoryBalance::query()->where('branch_id', $destination->id)->value('quantity'))->toBe(4);
});
