<?php

use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSequence;
use App\Models\StockMovement;
use App\Models\User;
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
function seededSaleContext(int $quantity = 10, int $sellingPrice = 1500): array
{
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = test()->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cooking Oil 1L',
        'sku' => 'OIL-1L',
        'barcode' => '8901234567890',
        'selling_price' => $sellingPrice,
        'cost_price' => 1000,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    return compact('owner', 'business', 'branch', 'product');
}

function salePayload(Branch $branch, Product $product, array $overrides = []): array
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
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        ...$overrides,
    ];
}

function clockInAs(User $user): void
{
    test()->clockInAndOpenDrawer($user);
}

it('completes a sale atomically with items payment stock and audit', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = seededSaleContext();

    $payload = salePayload($branch, $product, [
        'customer_name' => 'Walk-in Jane',
        'payment_method' => PaymentMethod::MobileMoney->value,
        'payment_reference' => 'MPESA-ABC',
    ]);

    $this->actingAs($owner)
        ->post(route('sales.store'), $payload)
        ->assertRedirect();

    $sale = Sale::query()->first();

    expect($sale)->not->toBeNull()
        ->and($sale->sale_number)->toBe('SAL-000001')
        ->and($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->payment_method)->toBe(PaymentMethod::MobileMoney)
        ->and($sale->subtotal)->toBe(3000)
        ->and($sale->total)->toBe(3000)
        ->and($sale->customer_name)->toBe('Walk-in Jane')
        ->and(SaleItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->value('amount'))->toBe(3000)
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(8)
        ->and(StockMovement::query()->where('type', StockMovementType::Sale)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'sale.completed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'inventory.sale')->exists())->toBeTrue()
        ->and(SaleSequence::query()->where('business_id', $sale->business_id)->value('last_number'))->toBe(1);

    $movement = StockMovement::query()->where('type', StockMovementType::Sale)->first();
    expect($movement?->reference_type)->toBe($sale->getMorphClass())
        ->and($movement?->reference_id)->toBe($sale->id)
        ->and($movement?->quantity_delta)->toBe(-2)
        ->and($movement?->quantity_after)->toBe(8);
});

it('rejects sales that exceed available stock', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = seededSaleContext(quantity: 3);

    $this->actingAs($owner)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]))
        ->assertSessionHasErrors('items.0.quantity');

    expect(Sale::query()->count())->toBe(0)
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(3)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('prevents duplicate sale submission with the same client request id', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = seededSaleContext();
    $requestId = (string) Str::uuid();

    $payload = salePayload($branch, $product, [
        'client_request_id' => $requestId,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $this->actingAs($owner)->post(route('sales.store'), $payload)->assertRedirect();
    $this->actingAs($owner)->post(route('sales.store'), $payload)->assertRedirect();

    expect(Sale::query()->count())->toBe(1)
        ->and(SaleItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(9)
        ->and(StockMovement::query()->where('type', StockMovementType::Sale)->count())->toBe(1);
});

it('voids a sale restoring stock and writing sale_void movement', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = seededSaleContext(quantity: 10);

    $this->actingAs($owner)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(6);

    $this->actingAs($owner)
        ->post(route('sales.void', $sale), [
            'reason' => 'Customer returned damaged item',
        ])
        ->assertRedirect();

    $sale->refresh();

    expect($sale->status)->toBe(SaleStatus::Voided)
        ->and($sale->void_reason)->toBe('Customer returned damaged item')
        ->and($sale->voided_at)->not->toBeNull()
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(10)
        ->and(StockMovement::query()->where('type', StockMovementType::SaleVoid)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'sale.voided')->exists())->toBeTrue();

    $voidMovement = StockMovement::query()->where('type', StockMovementType::SaleVoid)->first();
    expect($voidMovement?->quantity_delta)->toBe(4)
        ->and($voidMovement?->quantity_after)->toBe(10)
        ->and($voidMovement?->reference_id)->toBe($sale->id);
});

it('restricts void and discount to owner and manager roles', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch, 'product' => $product] = seededSaleContext();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    clockInAs($cashier);

    $this->actingAs($cashier)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'discount_amount' => '5.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]))
        ->assertSessionHasErrors('discount_amount');

    $this->actingAs($owner)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'discount_amount' => '5.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    expect($sale->discount_amount)->toBe(500)
        ->and($sale->total)->toBe(1000);

    $this->actingAs($cashier)
        ->post(route('sales.void', $sale), [
            'reason' => 'Cashier should not void',
        ])
        ->assertForbidden();

    expect($sale->fresh()->status)->toBe(SaleStatus::Completed);
});

it('enforces cashier branch access for completing sales', function () {
    ['business' => $business, 'branch' => $branch, 'product' => $product] = seededSaleContext();
    $otherBranch = Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Other Branch',
    ]);
    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $otherBranch->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    clockInAs($cashier);

    $this->actingAs($cashier)
        ->post(route('sales.store'), salePayload($otherBranch, $product))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    expect(Sale::query()->count())->toBe(1)
        ->and(Sale::query()->value('branch_id'))->toBe($branch->id);
});

it('isolates sales across tenants', function () {
    ['owner' => $ownerA, 'branch' => $branchA, 'product' => $productA] = seededSaleContext();
    ['owner' => $ownerB, 'branch' => $branchB, 'product' => $productB] = seededSaleContext();

    $this->actingAs($ownerA)
        ->post(route('sales.store'), salePayload($branchA, $productA, [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    $saleA = Sale::query()->firstOrFail();

    $this->actingAs($ownerB)
        ->get(route('sales.show', $saleA))
        ->assertNotFound();

    $this->actingAs($ownerB)
        ->post(route('sales.store'), salePayload($branchA, $productA))
        ->assertForbidden();

    $this->actingAs($ownerB)
        ->post(route('sales.store'), salePayload($branchB, $productB, [
            'items' => [
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    expect(Sale::query()->forBusiness($ownerA->current_business_id)->count())->toBe(1)
        ->and(Sale::query()->forBusiness($ownerB->current_business_id)->count())->toBe(1);
});

it('lists sales with filters and serves receipt plus pdf invoice', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch, 'product' => $product] = seededSaleContext();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Saved Customer',
    ]);

    $this->actingAs($owner)
        ->post(route('sales.store'), salePayload($branch, $product, [
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Card->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('sales.index', [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Card->value,
            'status' => SaleStatus::Completed->value,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $sale->id)
        );

    $this->actingAs($owner)
        ->get(route('sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/show')
            ->where('sale.sale_number', $sale->sale_number)
        );

    $this->actingAs($owner)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertSee($sale->sale_number)
        ->assertSee('Cooking Oil 1L');

    $this->actingAs($owner)
        ->get(route('sales.invoice', $sale))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('denies inventory clerks from sales while allowing cashiers on pos', function () {
    ['business' => $business, 'branch' => $branch] = seededSaleContext();
    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$branch->id]);
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($clerk)->get(route('sales.index'))->assertForbidden();
    $this->actingAs($clerk)->get(route('sales.pos'))->assertForbidden();
    $this->actingAs($clerk)->get(route('customers.index'))->assertForbidden();

    $this->actingAs($cashier)->get(route('sales.pos'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('sales/pos'));
    $this->actingAs($cashier)->get(route('customers.index'))->assertOk();
});

it('supports customer crud for managers and create for cashiers', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = seededSaleContext();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($owner)
        ->post(route('customers.store'), [
            'name' => 'Alice Buyer',
            'phone' => '+254711000111',
            'email' => 'alice@example.com',
        ])
        ->assertRedirect();

    $customer = Customer::query()->firstOrFail();

    $this->actingAs($cashier)
        ->post(route('customers.store'), [
            'name' => 'Cashier Created',
        ])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->patch(route('customers.update', $customer), [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('customers.update', $customer), [
            'name' => 'Alice Updated',
            'phone' => '+254711000111',
            'email' => 'alice@example.com',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('Alice Updated')
        ->and(Customer::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'customer.created')->count())->toBe(2);
});

it('sequences sale numbers per business', function () {
    ['owner' => $ownerA, 'branch' => $branchA, 'product' => $productA] = seededSaleContext();
    ['owner' => $ownerB, 'branch' => $branchB, 'product' => $productB] = seededSaleContext();

    $this->actingAs($ownerA)->post(route('sales.store'), salePayload($branchA, $productA, [
        'items' => [['product_id' => $productA->id, 'quantity' => 1]],
    ]))->assertRedirect();
    $this->actingAs($ownerA)->post(route('sales.store'), salePayload($branchA, $productA, [
        'items' => [['product_id' => $productA->id, 'quantity' => 1]],
    ]))->assertRedirect();
    $this->actingAs($ownerB)->post(route('sales.store'), salePayload($branchB, $productB, [
        'items' => [['product_id' => $productB->id, 'quantity' => 1]],
    ]))->assertRedirect();

    $numbersA = Sale::query()->forBusiness($ownerA->current_business_id)->orderBy('id')->pluck('sale_number')->all();
    $numbersB = Sale::query()->forBusiness($ownerB->current_business_id)->pluck('sale_number')->all();

    expect($numbersA)->toBe(['SAL-000001', 'SAL-000002'])
        ->and($numbersB)->toBe(['SAL-000001']);
});
