<?php

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SyncLink;
use App\Models\SyncStock;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use App\Services\Sync\ShopSyncService;
use App\Services\Sync\SyncApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('rejects a second register and shares stock through the sync api', function () {
    $payload = [
        'business_public_uuid' => (string) Str::uuid(),
        'business_name' => 'Twin Shops',
        'owner_email' => 'owner@twin.test',
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Till A',
    ];

    $registered = $this->postJson('/api/sync/accounts', $payload)
        ->assertCreated()
        ->json();

    $this->postJson('/api/sync/accounts', $payload)->assertStatus(422);

    $this->postJson('/api/sync/join', [
        'join_code' => $registered['join_code'],
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Till B',
    ])->assertOk()->assertJsonPath('business_name', 'Twin Shops');

    $branch = (string) Str::uuid();
    $product = (string) Str::uuid();

    $this->withToken($registered['token'])
        ->postJson('/api/sync/operations', [
            'operations' => [[
                'uuid' => (string) Str::uuid(),
                'entity_type' => 'stock_movements',
                'entity_uuid' => (string) Str::uuid(),
                'op' => 'upsert',
                'payload' => [
                    'type' => 'opening_stock',
                    'quantity_delta' => 1,
                    'branch_uuid' => $branch,
                    'product_uuid' => $product,
                ],
            ]],
        ])
        ->assertOk();

    $firstSale = (string) Str::uuid();

    $this->withToken($registered['token'])
        ->postJson('/api/sync/operations', [
            'operations' => [[
                'uuid' => $firstSale,
                'entity_type' => 'stock_movements',
                'entity_uuid' => (string) Str::uuid(),
                'op' => 'upsert',
                'payload' => [
                    'type' => 'sale',
                    'quantity_delta' => -1,
                    'branch_uuid' => $branch,
                    'product_uuid' => $product,
                    'sale_uuid' => (string) Str::uuid(),
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('conflicts', []);

    $secondSale = (string) Str::uuid();

    $this->withToken($registered['token'])
        ->postJson('/api/sync/operations', [
            'operations' => [[
                'uuid' => $secondSale,
                'entity_type' => 'stock_movements',
                'entity_uuid' => (string) Str::uuid(),
                'op' => 'upsert',
                'payload' => [
                    'type' => 'sale',
                    'quantity_delta' => -1,
                    'branch_uuid' => $branch,
                    'product_uuid' => $product,
                    'sale_uuid' => (string) Str::uuid(),
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('conflicts.0.uuid', $secondSale)
        ->assertJsonPath('conflicts.0.reason', 'insufficient_stock');

    expect(SyncStock::query()->value('quantity'))->toBe(0);

    $this->withToken($registered['token'])
        ->postJson('/api/sync/operations', [
            'operations' => [[
                'uuid' => $secondSale,
                'entity_type' => 'stock_movements',
                'entity_uuid' => (string) Str::uuid(),
                'op' => 'upsert',
                'payload' => [
                    'type' => 'sale',
                    'quantity_delta' => -1,
                    'branch_uuid' => $branch,
                    'product_uuid' => $product,
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('conflicts.0.reason', 'insufficient_stock');

    expect(SyncStock::query()->value('quantity'))->toBe(0);
});

it('copies a shop onto another computer and refuses the offline sale of the last item', function () {
    ['owner' => $ownerA, 'business' => $businessA, 'branch' => $branchA] = $this->createBusinessWithOwner();
    ['owner' => $ownerB, 'business' => $businessB] = $this->createBusinessWithOwner();

    $productA = Product::factory()->create([
        'business_id' => $businessA->id,
        'name' => 'Soap',
        'sku' => 'SOAP-1',
        'selling_price' => 1500,
        'cost_price' => 800,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $businessA->id,
        'branch_id' => $branchA->id,
        'product_id' => $productA->id,
        'quantity' => 1,
    ]);

    $sync = app(ShopSyncService::class);
    $sync->link($businessA, 'http://sync.test', 'Till A');
    $sync->join($businessB, $ownerB, 'http://sync.test', (string) SyncLink::query()->where('business_id', $businessA->id)->value('join_code'));

    $productB = Product::query()->where('business_id', $businessB->id)->where('sku', 'SOAP-1')->first();
    $branchB = Branch::query()->where('business_id', $businessB->id)->first();

    expect($productB)->not->toBeNull()
        ->and($branchB)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $branchB?->id)->where('product_id', $productB?->id)->value('quantity'))->toBe(1)
        ->and($productB?->public_uuid)->toBe($productA->public_uuid);

    $linkA = SyncLink::query()->where('business_id', $businessA->id)->firstOrFail();
    $linkB = SyncLink::query()->where('business_id', $businessB->id)->firstOrFail();

    $productA->update(['selling_price' => 1800]);
    $sync->run($linkA);
    $sync->run($linkB);

    expect($productB?->fresh()?->selling_price)->toBe(1800);

    $saleB = Sale::factory()->create([
        'business_id' => $businessB->id,
        'branch_id' => $branchB?->id,
        'cashier_id' => $ownerB->id,
        'sale_number' => 'SAL-B-0001',
    ]);

    SaleItem::factory()->create([
        'business_id' => $businessB->id,
        'sale_id' => $saleB->id,
        'product_id' => $productB?->id,
        'quantity' => 1,
        'product_name' => 'Soap',
        'sku' => 'SOAP-1',
    ]);

    $saleA = Sale::factory()->create([
        'business_id' => $businessA->id,
        'branch_id' => $branchA->id,
        'cashier_id' => $ownerA->id,
        'sale_number' => 'SAL-A-0001',
    ]);

    app(InventoryService::class)->applyMovement(
        business: $businessB,
        branch: $branchB ?? $branchA,
        product: $productB ?? $productA,
        type: StockMovementType::Sale,
        quantityDelta: -1,
        actor: $ownerB,
        reference: $saleB,
    );

    app(InventoryService::class)->applyMovement(
        business: $businessA,
        branch: $branchA,
        product: $productA,
        type: StockMovementType::Sale,
        quantityDelta: -1,
        actor: $ownerA,
        reference: $saleA,
    );

    $sync->run($linkA->fresh() ?? $linkA);
    $sync->run($linkB->fresh() ?? $linkB);

    expect($saleB->fresh()?->sync_conflict_at)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $branchB?->id)->where('product_id', $productB?->id)->value('quantity'))->toBe(0)
        ->and(Sale::query()->where('business_id', $businessB->id)->where('sale_number', 'SAL-A-0001')->exists())->toBeTrue();

    app(SaleService::class)->void($saleB->fresh() ?? $saleB, ['reason' => 'Sold on the other till'], $ownerB);

    expect($saleB->fresh()?->status->value)->toBe('voided')
        ->and(InventoryBalance::query()->where('branch_id', $branchB?->id)->where('product_id', $productB?->id)->value('quantity'))->toBe(0);
});

it('copies a staff password onto a computer that does not have that account yet', function () {
    ['business' => $business, 'owner' => $owner] = $this->createBusinessWithOwner();
    $link = app(ShopSyncService::class)->link($business, 'http://sync.test', 'Till A');
    $hash = Hash::make('secret-pass');

    app(SyncApplier::class)->apply($business, $link->fresh() ?? $link, [
        'uuid' => (string) Str::uuid(),
        'device_uuid' => (string) Str::uuid(),
        'entity_type' => 'users',
        'entity_uuid' => (string) Str::uuid(),
        'op' => 'upsert',
        'payload' => [
            'name' => 'Evening cashier',
            'email' => 'evening@shop.test',
            'password' => $hash,
        ],
    ]);

    $cashier = User::query()->where('email', 'evening@shop.test')->first();

    expect($cashier)->not->toBeNull()
        ->and(Hash::check('secret-pass', (string) $cashier?->password))->toBeTrue()
        ->and($owner->fresh()?->email)->not->toBe('evening@shop.test');
});

it('shows shop sync settings to the owner', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->get(route('shops.edit'))
        ->assertOk();
});
