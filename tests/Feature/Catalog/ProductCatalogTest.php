<?php

use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('creates products with prices stored as minor units and audit logs', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $category = Category::factory()->create([
        'business_id' => $business->id,
        'name' => 'Soap',
    ]);
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Geisha Bathing Soap 50g',
            'sku' => 'GEISHA-50',
            'barcode' => '1234567890123',
            'category_id' => $category->id,
            'description' => 'Bathing soap',
            'cost_price' => '12.50',
            'selling_price' => '18.00',
            'reorder_level' => 5,
            'is_active' => true,
            'supplier_ids' => [$supplier->id],
        ])
        ->assertRedirect();

    $product = Product::query()->forBusiness($business)->first();

    expect($product)->not->toBeNull()
        ->and($product->category_id)->toBe($category->id)
        ->and($product->cost_price)->toBe(Money::toMinor('12.50', 'KES'))
        ->and($product->selling_price)->toBe(Money::toMinor('18.00', 'KES'))
        ->and($product->suppliers()->pluck('suppliers.id')->all())->toBe([$supplier->id])
        ->and(AuditLog::query()->where('action', 'product.created')->where('business_id', $business->id)->exists())->toBeTrue();
});

it('supports flat categories and supplier product linking', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->post(route('categories.store'), [
            'name' => 'Soap',
            'description' => 'Bathing and washing soap',
        ])
        ->assertRedirect();

    $category = Category::query()->forBusiness($business)->firstOrFail();

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'White-Wash 20g',
    ]);

    $this->actingAs($owner)
        ->post(route('suppliers.store'), [
            'name' => 'Nairobi Distributors',
            'phone' => '+254700000000',
            'product_ids' => [$product->id],
        ])
        ->assertRedirect();

    $supplier = Supplier::query()->forBusiness($business)->firstOrFail();

    expect($category->name)->toBe('Soap')
        ->and($supplier->name)->toBe('Nairobi Distributors')
        ->and($supplier->products()->pluck('products.id')->all())->toBe([$product->id]);
});

it('isolates products across tenants', function () {
    ['owner' => $ownerA, 'business' => $businessA] = $this->createBusinessWithOwner();
    ['business' => $businessB] = $this->createBusinessWithOwner(['name' => 'Other Biz']);

    $productB = Product::factory()->create([
        'business_id' => $businessB->id,
        'name' => 'Secret Product',
    ]);

    $this->actingAs($ownerA)
        ->get(route('products.show', $productB))
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/index')
            ->has('products.data', 0)
        );

    expect(Product::query()->forBusiness($businessA)->whereKey($productB->id)->exists())->toBeFalse();
});

it('allows inventory clerks to manage catalog', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$branch->id]);

    $this->actingAs($clerk)
        ->post(route('products.store'), [
            'name' => 'Soap',
            'sku' => 'SOAP-01',
            'cost_price' => '1.00',
            'selling_price' => '2.00',
            'reorder_level' => 3,
        ])
        ->assertRedirect();

    expect(Product::query()->forBusiness($business)->where('sku', 'SOAP-01')->exists())->toBeTrue();
});

it('blocks deleting categories that still have products', function () {
    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $category = Category::factory()->create([
        'business_id' => $business->id,
        'name' => 'Soap',
    ]);

    Product::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('categories.destroy', $category))
        ->assertSessionHasErrors('category');
});
