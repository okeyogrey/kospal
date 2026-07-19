<?php

use App\Enums\BusinessRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('denies cashiers from products inventory and suppliers', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $product = Product::factory()->create(['business_id' => $business->id]);
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);
    $category = Category::factory()->create(['business_id' => $business->id]);

    $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('products.show', $product))->assertForbidden();
    $this->actingAs($cashier)->post(route('products.store'), [
        'name' => 'Nope',
        'sku' => 'NOPE',
        'cost_price' => '1',
        'selling_price' => '2',
    ])->assertForbidden();

    $this->actingAs($cashier)->get(route('inventory.index'))->assertForbidden();
    $this->actingAs($cashier)->post(route('inventory.receive-stock.store'), [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertForbidden();

    $this->actingAs($cashier)->get(route('suppliers.index'))->assertForbidden();
    $this->actingAs($cashier)->patch(route('suppliers.update', $supplier), [
        'name' => 'Hijack',
    ])->assertForbidden();

    $this->actingAs($cashier)->get(route('categories.index'))->assertForbidden();
    $this->actingAs($cashier)->delete(route('categories.destroy', $category))->assertForbidden();
});

it('prevents cross-tenant inventory mutations', function () {
    ['owner' => $ownerA] = $this->createBusinessWithOwner();
    ['business' => $businessB, 'branch' => $branchB] = $this->createBusinessWithOwner([
        'name' => 'Tenant B',
    ]);

    $productB = Product::factory()->create(['business_id' => $businessB->id]);

    $this->actingAs($ownerA)
        ->post(route('inventory.receive-stock.store'), [
            'branch_id' => $branchB->id,
            'product_id' => $productB->id,
            'quantity' => 9,
        ])
        ->assertForbidden();
});
