<?php

use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
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
function retailSaleContext(int $quantity = 20, int $sellingPrice = 1500, int $costPrice = 1000): array
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
        'cost_price' => $costPrice,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    return compact('owner', 'business', 'branch', 'product');
}

function retailSalePayload(Branch $branch, Product $product, array $overrides = []): array
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

it('looks up products by exact barcode for uninterrupted scanning', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext();

    $this->actingAs($owner)
        ->getJson(route('sales.pos.barcode', [
            'barcode' => $product->barcode,
            'branch_id' => $branch->id,
        ]))
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', $product->barcode);

    $this->actingAs($owner)
        ->getJson(route('sales.pos.barcode', [
            'barcode' => '0000000000000',
            'branch_id' => $branch->id,
        ]))
        ->assertNotFound();
});

it('completes a sale with split payments cash tendered and change', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext();

    $this->actingAs($owner)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'payment_method' => null,
            'cash_tendered' => '20.00',
            'change_given' => '5.00',
            'payments' => [
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => '15.00',
                    'tendered_amount' => '20.00',
                    'change_amount' => '5.00',
                ],
                [
                    'method' => PaymentMethod::MobileMoney->value,
                    'amount' => '15.00',
                    'reference' => 'MPESA-SPLIT',
                ],
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect($sale->total)->toBe(3000)
        ->and($sale->cash_tendered)->toBe(2000)
        ->and($sale->change_given)->toBe(500)
        ->and($sale->payment_method)->toBe(PaymentMethod::Cash)
        ->and(Payment::query()->count())->toBe(2)
        ->and(Payment::query()->sum('amount'))->toBe(3000);
});

it('snapshots historical cost and list price on sale items', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext(
        sellingPrice: 2000,
        costPrice: 1250,
    );

    $this->actingAs($owner)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
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

    $item = SaleItem::query()->firstOrFail();

    expect($item->unit_price)->toBe(1800)
        ->and($item->list_unit_price)->toBe(2000)
        ->and($item->unit_cost)->toBe(1250);
});

it('holds resumes and completes a parked sale', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext();

    $this->actingAs($owner)
        ->post(route('sales.hold'), [
            'branch_id' => $branch->id,
            'held_label' => 'Table 4',
            'discount_amount' => '0',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])
        ->assertRedirect(route('sales.pos'));

    $held = Sale::query()->firstOrFail();

    expect($held->status)->toBe(SaleStatus::Held)
        ->and($held->held_label)->toBe('Table 4')
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(20)
        ->and(AuditLog::query()->where('action', 'sale.held')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->postJson(route('sales.held.resume', $held))
        ->assertOk()
        ->assertJsonPath('sale.id', $held->id)
        ->assertJsonPath('sale.items.0.quantity', 3);

    expect(AuditLog::query()->where('action', 'sale.resumed')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'held_sale_id' => $held->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]))
        ->assertRedirect();

    $held->refresh();

    expect($held->status)->toBe(SaleStatus::Completed)
        ->and($held->sale_number)->toStartWith('SAL-')
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(17)
        ->and(Sale::query()->where('status', SaleStatus::Held)->count())->toBe(0);
});

it('requires manager approval for negotiated prices by cashiers', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch, 'product' => $product] = retailSaleContext();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->clockInAndOpenDrawer($cashier);

    $this->actingAs($cashier)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '10.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertSessionHasErrors('manager_approval');

    $this->actingAs($cashier)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'manager_approval' => [
                'login' => $owner->email,
                'password' => 'password',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '10.00',
                    'list_unit_price' => '15.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect($sale->total)->toBe(1000)
        ->and($sale->approved_by)->toBe($owner->id)
        ->and(SaleItem::query()->value('unit_price'))->toBe(1000);
});

it('processes partial returns restoring stock and writing audits', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext(quantity: 10);

    $this->actingAs($owner)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $item = $sale->items()->firstOrFail();

    expect(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(6);

    $this->actingAs($owner)
        ->post(route('sales.returns', $sale), [
            'reason' => 'Customer returned one bottle',
            'refund_method' => PaymentMethod::Cash->value,
            'client_request_id' => (string) Str::uuid(),
            'items' => [
                ['sale_item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    $item->refresh();
    $saleReturn = SaleReturn::query()->firstOrFail();

    expect($item->returned_quantity)->toBe(1)
        ->and($saleReturn->return_number)->toBe('RET-000001')
        ->and($saleReturn->total)->toBe(1500)
        ->and(InventoryBalance::query()->where('branch_id', $branch->id)->value('quantity'))->toBe(7)
        ->and(StockMovement::query()->where('type', StockMovementType::Return)->count())->toBe(1)
        ->and(Payment::query()->where('amount', -1500)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'sale.returned')->exists())->toBeTrue();
});

it('audits receipt reprints', function () {
    ['owner' => $owner, 'branch' => $branch, 'product' => $product] = retailSaleContext();

    $this->actingAs($owner)
        ->post(route('sales.store'), retailSalePayload($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]))
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('sales.receipt.reprint', $sale))
        ->assertRedirect(route('sales.receipt', ['sale' => $sale, 'reprint' => 1]));

    expect(AuditLog::query()->where('action', 'sale.receipt_reprinted')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('sales.receipt', ['sale' => $sale, 'reprint' => 1]))
        ->assertOk()
        ->assertSee('Reprint');
});
