<?php

use App\Enums\BusinessRole;
use App\Enums\PaymentMethod;
use App\Enums\Plan;
use App\Enums\ReportType;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    $this->withoutVite();
});

function seedSaleForBranch(
    int $businessId,
    int $branchId,
    int $cashierId,
    int $total,
    ?Product $product = null,
    ?CarbonInterface $createdAt = null,
): Sale {
    $sale = Sale::factory()->create([
        'business_id' => $businessId,
        'branch_id' => $branchId,
        'cashier_id' => $cashierId,
        'status' => SaleStatus::Completed,
        'payment_method' => PaymentMethod::Cash,
        'currency' => 'KES',
        'subtotal' => $total,
        'discount_amount' => 0,
        'total' => $total,
        'created_at' => $createdAt ?? now(),
        'updated_at' => $createdAt ?? now(),
    ]);

    if ($product !== null) {
        SaleItem::factory()->create([
            'business_id' => $businessId,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => intdiv($total, 2),
            'line_total' => $total,
        ]);
    }

    return $sale;
}

it('allows owners to view the dashboard with scoped metrics', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 100,
        'name' => 'Soap',
        'sku' => 'SOAP-1',
    ]);

    seedSaleForBranch($business->id, $branch->id, $owner->id, 5000, $product);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    $product->update(['reorder_level' => 5]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('metrics.today_sales_minor', 5000)
            ->where('metrics.sales_count', 1)
            ->where('metrics.low_stock_count', 1)
            ->where('currency', 'KES')
            ->has('recent_sales', 1)
            ->has('top_products', 1)
        );
});

it('allows cashiers to view dashboard but not reports', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permissions.view_reports', false)
        );

    $this->actingAs($cashier)
        ->get(route('reports.index'))
        ->assertForbidden();
});

it('lists starter reports as available and advanced reports as locked', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $this->actingAs($owner)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('plan', 'starter')
            ->where('features.advanced_reports', false)
            ->where('features.csv_export', false)
            ->where('reports.sales.0.key', 'sales_summary')
            ->where('reports.sales.0.available', true)
            ->where('reports.sales.1.key', ReportType::SalesTrends->value)
            ->where('reports.sales.1.available', false)
        );
});

it('shows an upgrade screen for locked advanced reports on starter', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $this->actingAs($owner)
        ->get(route('reports.show', ReportType::SalesTrends->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/show')
            ->where('report.available', false)
            ->where('upgrade.required_plan', 'pro')
            ->where('rows', [])
        );
});

it('allows pro plans to open advanced reports and export csv', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
        'currency' => 'KES',
    ]);

    seedSaleForBranch($business->id, $branch->id, $owner->id, 2500);

    $this->actingAs($owner)
        ->get(route('reports.show', [
            'report' => ReportType::SalesByPaymentMethod->value,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.available', true)
            ->where('permissions.export', true)
            ->has('rows', 1)
        );

    $this->actingAs($owner)
        ->get(route('reports.export', ReportType::SalesSummary->value))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('denies csv export on starter plans', function () {
    ['owner' => $owner] = $this->createBusinessWithOwner([
        'plan' => Plan::Starter,
    ]);

    $this->actingAs($owner)
        ->get(route('reports.export', ReportType::SalesSummary->value))
        ->assertForbidden();
});

it('keeps report data isolated between businesses', function () {
    ['owner' => $ownerA, 'business' => $businessA, 'branch' => $branchA] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
        'name' => 'Alpha Shop',
    ]);
    ['owner' => $ownerB, 'business' => $businessB, 'branch' => $branchB] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
        'name' => 'Beta Shop',
    ]);

    seedSaleForBranch($businessA->id, $branchA->id, $ownerA->id, 9000);
    seedSaleForBranch($businessB->id, $branchB->id, $ownerB->id, 1000);

    $this->actingAs($ownerA)
        ->get(route('reports.show', [
            'report' => ReportType::SalesSummary->value,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.sales_total_minor', 9000)
            ->where('summary.sales_count', 1)
        );

    $this->actingAs($ownerB)
        ->get(route('reports.show', [
            'report' => ReportType::SalesSummary->value,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.sales_total_minor', 1000)
            ->where('summary.sales_count', 1)
        );
});

it('rejects cross-business branch filters with 403', function () {
    ['owner' => $ownerA] = $this->createBusinessWithOwner(['plan' => Plan::Pro]);
    ['branch' => $branchB] = $this->createBusinessWithOwner(['plan' => Plan::Pro]);

    $this->actingAs($ownerA)
        ->get(route('reports.show', [
            'report' => ReportType::SalesSummary->value,
            'branch_id' => $branchB->id,
        ]))
        ->assertForbidden();
});

it('gates consolidated branch comparison to enterprise', function () {
    ['owner' => $proOwner] = $this->createBusinessWithOwner(['plan' => Plan::Pro]);
    ['owner' => $enterpriseOwner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
    ]);

    Branch::factory()->create([
        'business_id' => $business->id,
        'name' => 'Second',
    ]);

    seedSaleForBranch($business->id, $branch->id, $enterpriseOwner->id, 4000);

    $this->actingAs($proOwner)
        ->get(route('reports.show', ReportType::BranchComparison->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.available', false)
            ->where('upgrade.required_plan', 'enterprise')
        );

    $this->actingAs($enterpriseOwner)
        ->get(route('reports.show', ReportType::BranchComparison->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.available', true)
            ->has('rows', 2)
        );
});

it('gates audit log report to enterprise and scopes by business', function () {
    ['owner' => $proOwner] = $this->createBusinessWithOwner(['plan' => Plan::Pro]);
    ['owner' => $enterpriseOwner, 'business' => $business] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
    ]);
    ['business' => $other] = $this->createBusinessWithOwner([
        'plan' => Plan::Enterprise,
    ]);

    AuditLog::query()->create([
        'business_id' => $business->id,
        'user_id' => $enterpriseOwner->id,
        'action' => 'sale.created',
        'auditable_type' => Sale::class,
        'auditable_id' => 1,
        'created_at' => now(),
    ]);

    AuditLog::query()->create([
        'business_id' => $other->id,
        'user_id' => null,
        'action' => 'sale.created',
        'auditable_type' => Sale::class,
        'auditable_id' => 2,
        'created_at' => now(),
    ]);

    $this->actingAs($proOwner)
        ->get(route('reports.show', ReportType::AuditLogs->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.available', false));

    $this->actingAs($enterpriseOwner)
        ->get(route('reports.show', ReportType::AuditLogs->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.available', true)
            ->has('rows', 1)
            ->where('rows.0.action', 'sale.created')
        );
});

it('computes starter expenses and low stock reports', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();

    $category = ExpenseCategory::factory()->create([
        'business_id' => $business->id,
        'name' => 'Rent',
    ]);

    Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'created_by' => $owner->id,
        'expense_date' => now()->toDateString(),
        'amount' => 15000,
        'currency' => 'KES',
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'reorder_level' => 10,
    ]);

    InventoryBalance::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->actingAs($owner)
        ->get(route('reports.show', ReportType::ExpensesSummary->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.expenses_total_minor', 15000)
            ->has('rows', 1)
        );

    $this->actingAs($owner)
        ->get(route('reports.show', ReportType::LowStock->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.alert_count', 1)
            ->has('rows', 1)
        );
});

it('estimates gross profit from product cost prices on pro', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'plan' => Plan::Pro,
    ]);

    $product = Product::factory()->create([
        'business_id' => $business->id,
        'cost_price' => 1000,
        'name' => 'Oil',
        'sku' => 'OIL-1',
    ]);

    seedSaleForBranch($business->id, $branch->id, $owner->id, 5000, $product);

    $this->actingAs($owner)
        ->get(route('reports.show', ReportType::GrossProfit->value))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.revenue_minor', 5000)
            ->where('summary.cost_minor', 2000)
            ->where('summary.gross_profit_minor', 3000)
        );
});
