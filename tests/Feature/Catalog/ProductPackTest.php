<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductPack;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('creates a product with a carton pack and base unit label', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Sugar 1kg',
            'sku' => 'SUGAR-1KG',
            'barcode' => '6001001001001',
            'base_unit_name' => 'packet',
            'cost_price' => '80.00',
            'selling_price' => '100.00',
            'reorder_level' => 10,
            'is_active' => true,
            'packs' => [
                [
                    'name' => 'Carton',
                    'units_per_pack' => 24,
                    'barcode' => '6001001001024',
                    'selling_price' => '2200.00',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::query()->forBusiness($business)->firstOrFail();
    $pack = $product->packs()->firstOrFail();

    expect($product->base_unit_name)->toBe('packet')
        ->and($pack->name)->toBe('Carton')
        ->and($pack->units_per_pack)->toBe(24)
        ->and($pack->barcode)->toBe('6001001001024')
        ->and($pack->selling_price)->toBe(220000);
});

it('receives stock in cartons and stores base unit quantity', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'base_unit_name' => 'packet',
        'selling_price' => 10000,
        'cost_price' => 8000,
    ]);

    $pack = ProductPack::factory()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'name' => 'Carton',
        'units_per_pack' => 24,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'product_pack_id' => $pack->id,
            'quantity' => 10,
            'note' => '10 cartons received',
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->value('quantity'))->toBe(240);
});

it('sells a carton pack and deducts base units while recording pack on the sale item', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'base_unit_name' => 'packet',
        'selling_price' => 10000,
        'cost_price' => 8000,
    ]);

    $pack = ProductPack::factory()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'name' => 'Carton',
        'units_per_pack' => 24,
        'selling_price' => 220000,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 240,
    ]);

    $this->actingAs($owner)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'discount_amount' => '0',
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_pack_id' => $pack->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $item = SaleItem::query()->where('sale_id', $sale->id)->firstOrFail();

    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($item->product_pack_id)->toBe($pack->id)
        ->and($item->pack_quantity)->toBe(1)
        ->and($item->pack_name)->toBe('Carton')
        ->and($item->quantity)->toBe(1)
        ->and($item->unit_price)->toBe(220000)
        ->and(InventoryBalance::query()
            ->where('product_id', $product->id)
            ->value('quantity'))->toBe(216);
});

it('sells a base unit without a pack and deducts one', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'selling_price' => 10000,
        'cost_price' => 8000,
    ]);

    ProductPack::factory()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'units_per_pack' => 24,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    $this->actingAs($owner)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'discount_amount' => '0',
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $item = SaleItem::query()->firstOrFail();

    expect($item->product_pack_id)->toBeNull()
        ->and($item->pack_quantity)->toBeNull()
        ->and($item->quantity)->toBe(1)
        ->and(InventoryBalance::query()
            ->where('product_id', $product->id)
            ->value('quantity'))->toBe(49);
});

it('looks up a product by pack barcode', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'barcode' => 'PRODUCT-BAR',
        'selling_price' => 10000,
    ]);

    $pack = ProductPack::factory()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'name' => 'Carton',
        'units_per_pack' => 24,
        'barcode' => 'PACK-CARTON-24',
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 48,
    ]);

    $this->actingAs($owner)
        ->getJson(route('sales.pos.barcode', [
            'barcode' => 'PACK-CARTON-24',
            'branch_id' => $branch->id,
        ]))
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product_pack_id', $pack->id);
});

it('keeps existing products without packs behaving as before on receive and sell', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'selling_price' => 1500,
        'cost_price' => 1000,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(10);

    $this->actingAs($owner)
        ->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => PaymentMethod::Cash->value,
            'discount_amount' => '0',
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(8)
        ->and(SaleItem::query()->where('product_id', $product->id)->value('product_pack_id'))->toBeNull();
});
