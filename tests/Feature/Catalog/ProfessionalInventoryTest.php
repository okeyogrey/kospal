<?php

use App\Enums\BusinessRole;
use App\Enums\GoodsReceivedNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\Plan;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Enums\SupplierInvoiceStatus;
use App\Models\AuditLog;
use App\Models\GoodsReceivedNote;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

function proBusiness(): array
{
    return test()->createBusinessWithOwner(['plan' => Plan::Pro]);
}

it('runs purchase order to grn to invoice to payment with weighted average cost', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = proBusiness();

    $supplier = Supplier::factory()->create(['business_id' => $business->id]);
    $product = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 1000,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($owner)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 2000],
            ],
        ])
        ->assertRedirect();

    $order = PurchaseOrder::query()->first();
    expect($order?->status)->toBe(PurchaseOrderStatus::Draft)
        ->and(AuditLog::query()->where('action', 'purchase_order.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('purchase-orders.send', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Sent);

    $this->actingAs($owner)
        ->post(route('goods-received.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'purchase_order_id' => $order->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_cost' => 2000,
                    'purchase_order_item_id' => $order->items()->first()->id,
                ],
            ],
        ])
        ->assertRedirect();

    $grn = GoodsReceivedNote::query()->first();
    expect($grn?->status)->toBe(GoodsReceivedNoteStatus::Draft);

    $this->actingAs($owner)
        ->post(route('goods-received.post', $grn), ['create_invoice' => true])
        ->assertRedirect();

    $grn->refresh();
    $product->refresh();
    $order->refresh();

    expect($grn->status)->toBe(GoodsReceivedNoteStatus::Posted)
        ->and(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(15)
        // WAC: ((10 * 1000) + (5 * 2000)) / 15 = 1333
        ->and($product->cost_price)->toBe(1333)
        ->and($order->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt)->count())->toBe(1)
        ->and(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt)->value('unit_cost'))->toBe(2000)
        ->and(ProductCostHistory::query()->where('product_id', $product->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'goods_received.posted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'product.cost_updated')->exists())->toBeTrue();

    $invoice = SupplierInvoice::query()->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::Posted)
        ->and($invoice->total)->toBe(10000);

    $this->actingAs($owner)
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'method' => PaymentMethod::Cash->value,
            'amount' => 4000,
            'paid_at' => now()->toDateString(),
            'allocations' => [
                ['supplier_invoice_id' => $invoice->id, 'amount' => 4000],
            ],
        ])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(SupplierInvoiceStatus::PartiallyPaid)
        ->and($invoice->amount_paid)->toBe(4000)
        ->and(SupplierPayment::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'supplier_payment.recorded')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'method' => PaymentMethod::MobileMoney->value,
            'amount' => 6000,
            'paid_at' => now()->toDateString(),
            'allocations' => [
                ['supplier_invoice_id' => $invoice->id, 'amount' => 6000],
            ],
        ])
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::Paid);
});

it('completes a stock count and posts variance movements', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = proBusiness();
    $product = Product::factory()->create(['business_id' => $business->id]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $this->actingAs($owner)
        ->post(route('stock-counts.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect();

    $count = StockCount::query()->first();
    expect($count?->status)->toBe(StockCountStatus::Draft)
        ->and($count->items()->count())->toBe(1);

    $this->actingAs($owner)
        ->post(route('stock-counts.start', $count))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('stock-counts.record', $count), [
            'counts' => [
                ['product_id' => $product->id, 'counted_quantity' => 7],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('stock-counts.complete', $count))
        ->assertRedirect();

    expect($count->fresh()->status)->toBe(StockCountStatus::Completed)
        ->and(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(7)
        ->and(StockMovement::query()->where('type', StockMovementType::StockCountVariance)->value('quantity_delta'))->toBe(-3)
        ->and(AuditLog::query()->where('action', 'stock_count.completed')->exists())->toBeTrue();
});

it('allows bidirectional stock adjustments with found reason', function () {
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
            'quantity' => 3,
            'direction' => 'increase',
            'reason' => 'found',
            'note' => 'Found on shelf during cleanup',
        ])
        ->assertRedirect();

    expect(InventoryBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(5);
});

it('renders timeline and valuation pages', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $product = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 500,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 4,
    ]);

    $this->actingAs($owner)
        ->get(route('inventory.timeline'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inventory/timeline'));

    $this->actingAs($owner)
        ->get(route('inventory.valuation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/valuation')
            ->where('summary.total_value', 2000)
            ->where('summary.total_quantity', 4));
});

it('gates purchase orders behind the pro plan feature', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);
    $product = Product::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => 100],
            ],
        ])
        ->assertStatus(422);
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = proBusiness();
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);
    $product = Product::factory()->create(['business_id' => $business->id, 'cost_price' => 100]);

    $this->actingAs($owner)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $product->id, 'quantity_ordered' => 4, 'unit_cost' => 250],
        ],
    ]);

    $order = PurchaseOrder::query()->firstOrFail();
    $this->actingAs($owner)->post(route('purchase-orders.send', $order));

    $this->actingAs($owner)->post(route('goods-received.store'), [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'purchase_order_id' => $order->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 4,
                'unit_cost' => 250,
                'purchase_order_item_id' => $order->items()->first()->id,
            ],
        ],
    ]);

    $grn = GoodsReceivedNote::query()->firstOrFail();
    $this->actingAs($owner)->post(route('goods-received.post', $grn));

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('restricts inventory clerks from supplier payments', function () {
    ['business' => $business, 'branch' => $branch] = proBusiness();
    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$branch->id]);
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);

    $this->actingAs($clerk)
        ->get(route('supplier-payments.index'))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'method' => PaymentMethod::Cash->value,
            'amount' => 100,
            'paid_at' => now()->toDateString(),
            'allocations' => [
                ['supplier_invoice_id' => 1, 'amount' => 100],
            ],
        ])
        ->assertForbidden();
});
