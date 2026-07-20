<?php

use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\Plan;
use App\Enums\ReportType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Pricing\NegotiationScoreService;
use App\Services\Pricing\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

/**
 * @return array{
 *     owner: User,
 *     business: Business,
 *     branch: Branch,
 *     product: Product
 * }
 */
function pricingContext(
    int $quantity = 20,
    int $sellingPrice = 1500,
    int $costPrice = 1000,
    int $minSellingPrice = 1200,
    bool $negotiable = true,
): array {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = test()->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cooking Oil 1L',
        'sku' => 'OIL-1L',
        'barcode' => '8901234567890',
        'selling_price' => $sellingPrice,
        'cost_price' => $costPrice,
        'min_selling_price' => $minSellingPrice,
        'is_negotiable' => $negotiable,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    $ownerMembership = BusinessMembership::query()
        ->where('business_id', $business->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();
    $ownerMembership->forceFill(['approval_pin' => '2468'])->save();

    return compact('owner', 'business', 'branch', 'product');
}

function pricingSalePayload(Branch $branch, Product $product, array $overrides = []): array
{
    return [
        'branch_id' => $branch->id,
        'customer_id' => null,
        'customer_name' => null,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_reference' => null,
        'discount_amount' => '0',
        'notes' => null,
        'client_request_id' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        ...$overrides,
    ];
}

it('stores pricing snapshots including profit margin and negotiated difference', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = pricingContext();

    $this->actingAs($owner)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => '13.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $item = SaleItem::query()->firstOrFail();

    expect($item->unit_cost)->toBe(1000)
        ->and($item->list_unit_price)->toBe(1500)
        ->and($item->unit_price)->toBe(1300)
        ->and($item->negotiated_difference)->toBe(200)
        ->and($item->profit)->toBe(600)
        ->and($item->margin_bps)->toBe(2307)
        ->and($item->manager_approved)->toBeFalse();
});

it('blocks cashiers from negotiating non-negotiable products', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch, 'product' => $product] = pricingContext(
        negotiable: false,
    );
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->clockInAndOpenDrawer($cashier);

    $this->actingAs($cashier)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '14.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertSessionHasErrors('items.0.unit_price');

    expect(Sale::query()->count())->toBe(0);
});

it('rejects prices below the product minimum selling price', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = pricingContext(
        minSellingPrice: 1200,
    );

    $this->actingAs($owner)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '10.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertSessionHasErrors('items.0.unit_price');
});

it('allows cashiers to negotiate within their floor without manager pin', function () {
    ['business' => $business, 'branch' => $branch, 'product' => $product] = pricingContext(
        sellingPrice: 2000,
        costPrice: 1000,
        minSellingPrice: 1000,
    );
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    BusinessMembership::query()
        ->where('user_id', $cashier->id)
        ->where('business_id', $business->id)
        ->update(['negotiation_floor_percent' => 90]);

    $this->clockInAndOpenDrawer($cashier);

    // 90% of 20.00 = 18.00 — within permission, no PIN.
    $this->actingAs($cashier)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '18.00',
                    'list_unit_price' => '20.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $item = SaleItem::query()->firstOrFail();

    expect($sale->approved_by)->toBeNull()
        ->and($item->unit_price)->toBe(1800)
        ->and($item->manager_approved)->toBeFalse();
});

it('requires manager pin when price falls below cashier permission floor', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch, 'product' => $product] = pricingContext(
        sellingPrice: 2000,
        costPrice: 1000,
        minSellingPrice: 1000,
    );
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    BusinessMembership::query()
        ->where('user_id', $cashier->id)
        ->where('business_id', $business->id)
        ->update(['negotiation_floor_percent' => 90]);

    $this->clockInAndOpenDrawer($cashier);

    $this->actingAs($cashier)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '15.00',
                    'list_unit_price' => '20.00',
                ],
            ],
        ]))
        ->assertSessionHasErrors('manager_approval');

    $this->actingAs($cashier)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'manager_approval' => [
                'pin' => '2468',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '15.00',
                    'list_unit_price' => '20.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $item = SaleItem::query()->firstOrFail();

    expect($sale->approved_by)->toBe($owner->id)
        ->and($item->manager_approved)->toBeTrue()
        ->and($item->negotiated_difference)->toBe(500);
});

it('computes a negotiation score from price fidelity margin overrides and consistency', function () {
    $service = app(NegotiationScoreService::class);

    $high = $service->score(
        avgPriceRatioBps: 9800,
        avgMarginBps: 4000,
        excessiveOverrides: 0,
        negotiatedLines: 10,
        priceRatios: [0.98, 0.97, 0.99, 1.0],
    );

    $low = $service->score(
        avgPriceRatioBps: 7000,
        avgMarginBps: 500,
        excessiveOverrides: 8,
        negotiatedLines: 10,
        priceRatios: [0.5, 1.2, 0.7, 0.9],
    );

    expect($high)->toBeGreaterThan($low)
        ->and($high)->toBeGreaterThan(70)
        ->and($low)->toBeLessThan(50);
});

it('shows negotiation performance report for owners', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = test()->createBusinessWithOwner([
        'currency' => 'KES',
        'plan' => Plan::Pro,
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'selling_price' => 1500,
        'cost_price' => 1000,
        'min_selling_price' => 1200,
        'is_negotiable' => true,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    $this->actingAs($owner)
        ->post(route('sales.store'), pricingSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '14.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('reports.show', ReportType::NegotiationPerformance->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/show')
            ->has('rows')
            ->where('report.key', ReportType::NegotiationPerformance->value)
            ->where('report.available', true));
});

it('calculates cashier permission floor from membership percent and product minimum', function () {
    $engine = app(PricingEngine::class);
    $product = new Product([
        'selling_price' => 2000,
        'min_selling_price' => 1500,
    ]);
    $membership = new BusinessMembership([
        'role' => BusinessRole::Cashier,
        'negotiation_floor_percent' => 80,
    ]);

    // 80% of 2000 = 1600, but min is 1500 → floor 1600
    expect($engine->cashierPermissionFloor($product, $membership))->toBe(1600);

    $membership->negotiation_floor_percent = 70;
    // 70% of 2000 = 1400, raised to min 1500
    expect($engine->cashierPermissionFloor($product, $membership))->toBe(1500);
});
