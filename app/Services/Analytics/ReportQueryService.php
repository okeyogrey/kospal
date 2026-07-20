<?php

namespace App\Services\Analytics;

use App\Enums\PaymentMethod;
use App\Enums\ReportType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StaffShift;
use App\Models\StockMovement;
use App\Services\Pricing\NegotiationScoreService;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\DateGrouping;
use App\Support\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function __construct(
        protected NegotiationScoreService $negotiationScores,
    ) {}

    /**
     * @return array{summary?: array<string, mixed>, rows: list<array<string, mixed>>, chart?: list<array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function run(ReportType $type, AnalyticsFilter $filter): array
    {
        if ($filter->branchIds() === [] && $type !== ReportType::AuditLogs) {
            return ['rows' => [], 'chart' => [], 'summary' => $this->zeroMoneySummary($filter->currency())];
        }

        return match ($type) {
            ReportType::SalesSummary => $this->salesSummary($filter),
            ReportType::ExpensesSummary => $this->expensesSummary($filter),
            ReportType::LowStock => $this->lowStock($filter),
            ReportType::SalesTrends => $this->salesTrends($filter),
            ReportType::SalesByBranch => $this->salesByBranch($filter),
            ReportType::SalesByCashier => $this->salesByCashier($filter),
            ReportType::SalesByPaymentMethod => $this->salesByPaymentMethod($filter),
            ReportType::ShiftReport => $this->shiftReport($filter),
            ReportType::EndOfDay => $this->endOfDay($filter),
            ReportType::CashReconciliation => $this->cashReconciliation($filter),
            ReportType::ProductPerformance => $this->productPerformance($filter),
            ReportType::SlowMovingProducts => $this->slowMovingProducts($filter),
            ReportType::InventoryValue => $this->inventoryValue($filter),
            ReportType::StockMovementSummary => $this->stockMovementSummary($filter),
            ReportType::ExpenseTrend => $this->expenseTrend($filter),
            ReportType::GrossProfit => $this->grossProfit($filter),
            ReportType::NegotiationPerformance => $this->negotiationScores->salespersonRanking($filter),
            ReportType::BranchComparison => $this->branchComparison($filter),
            ReportType::AuditLogs => $this->auditLogs($filter),
        };
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(ReportType $type, AnalyticsFilter $filter): array
    {
        $payload = $this->run($type, $filter);
        $rows = $payload['rows'] ?? [];

        if ($rows === []) {
            return [['message'], ['No data for the selected filters']];
        }

        $headers = array_keys($rows[0]);
        $csv = [$headers];

        foreach ($rows as $row) {
            $csv[] = array_map(
                static fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value,
                array_values($row),
            );
        }

        return $csv;
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function salesSummary(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();
        $statuses = $filter->includeVoided
            ? [SaleStatus::Completed->value, SaleStatus::Voided->value]
            : [SaleStatus::Completed->value];

        $aggregate = Sale::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereIn('status', $statuses)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total ELSE 0 END), 0) as sales_total', [SaleStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as sales_count', [SaleStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as voided_count', [SaleStatus::Voided->value])
            ->first();

        $total = (int) ($aggregate->sales_total ?? 0);
        $count = (int) ($aggregate->sales_count ?? 0);
        $aov = $count > 0 ? intdiv($total, $count) : 0;

        $byDay = $this->salesGrouped($filter, 'day');

        return [
            'summary' => [
                'sales_total_minor' => $total,
                'sales_total_formatted' => Money::format($total, $currency),
                'sales_count' => $count,
                'voided_count' => (int) ($aggregate->voided_count ?? 0),
                'average_order_value_minor' => $aov,
                'average_order_value_formatted' => Money::format($aov, $currency),
            ],
            'rows' => $byDay,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['period'],
                'value' => $row['sales_total_minor'],
            ], $byDay),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function expensesSummary(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $aggregates = Expense::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereDate('expense_date', '>=', $filter->dateFrom->toDateString())
            ->whereDate('expense_date', '<=', $filter->dateTo->toDateString())
            ->groupBy('expense_category_id')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get([
                'expense_category_id',
                DB::raw('SUM(amount) as total_minor'),
                DB::raw('COUNT(*) as expense_count'),
            ]);

        $categoryNames = ExpenseCategory::query()
            ->forBusiness($filter->business)
            ->whereIn('id', $aggregates->pluck('expense_category_id')->filter()->all())
            ->pluck('name', 'id');

        $rows = $aggregates->map(function ($row) use ($currency, $categoryNames) {
            $total = (int) $row->total_minor;

            return [
                'category_id' => $row->expense_category_id,
                'category_name' => $categoryNames[$row->expense_category_id] ?? 'Uncategorized',
                'total_minor' => $total,
                'total_formatted' => Money::format($total, $currency),
                'expense_count' => (int) $row->expense_count,
            ];
        })->all();

        $grand = array_sum(array_column($rows, 'total_minor'));

        return [
            'summary' => [
                'expenses_total_minor' => $grand,
                'expenses_total_formatted' => Money::format($grand, $currency),
                'category_count' => count($rows),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['category_name'],
                'value' => $row['total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    protected function lowStock(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = InventoryBalance::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->lowStock()
            ->with([
                'branch:id,name',
                'product:id,name,sku,reorder_level,cost_price',
            ])
            ->orderBy('quantity')
            ->limit(500)
            ->get()
            ->map(function (InventoryBalance $balance) use ($currency) {
                $value = $balance->inventoryValueMinor();

                return [
                    'branch_name' => $balance->branch?->name,
                    'product_name' => $balance->product?->name,
                    'sku' => $balance->product?->sku,
                    'quantity' => $balance->quantity,
                    'reorder_level' => $balance->product?->reorder_level,
                    'value_minor' => $value,
                    'value_formatted' => Money::format($value, $currency),
                ];
            })
            ->all();

        return [
            'summary' => [
                'alert_count' => count($rows),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function salesTrends(AnalyticsFilter $filter): array
    {
        $rows = $this->salesGrouped($filter, $filter->grain);
        $total = array_sum(array_column($rows, 'sales_total_minor'));

        return [
            'summary' => [
                'sales_total_minor' => $total,
                'sales_total_formatted' => Money::format($total, $filter->currency()),
                'period_count' => count($rows),
                'grain' => $filter->grain,
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['period'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function salesByBranch(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = Sale::query()
            ->from('sales')
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->where('sales.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sales.branch_id', 'branches.name')
            ->orderByDesc(DB::raw('SUM(sales.total)'))
            ->get([
                'sales.branch_id',
                'branches.name as branch_name',
                DB::raw('SUM(sales.total) as sales_total_minor'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(fn ($row) => [
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name,
                'sales_total_minor' => (int) $row->sales_total_minor,
                'sales_total_formatted' => Money::format((int) $row->sales_total_minor, $currency),
                'sales_count' => (int) $row->sales_count,
            ])
            ->all();

        $total = array_sum(array_column($rows, 'sales_total_minor'));

        return [
            'summary' => [
                'sales_total_minor' => $total,
                'sales_total_formatted' => Money::format($total, $currency),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['branch_name'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function salesByCashier(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = Sale::query()
            ->from('sales')
            ->leftJoin('users', 'users.id', '=', 'sales.cashier_id')
            ->where('sales.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sales.cashier_id', 'users.name')
            ->orderByDesc(DB::raw('SUM(sales.total)'))
            ->get([
                'sales.cashier_id',
                DB::raw("COALESCE(users.name, 'Unknown') as cashier_name"),
                DB::raw('SUM(sales.total) as sales_total_minor'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(fn ($row) => [
                'cashier_id' => $row->cashier_id,
                'cashier_name' => $row->cashier_name,
                'sales_total_minor' => (int) $row->sales_total_minor,
                'sales_total_formatted' => Money::format((int) $row->sales_total_minor, $currency),
                'sales_count' => (int) $row->sales_count,
            ])
            ->all();

        $total = array_sum(array_column($rows, 'sales_total_minor'));

        return [
            'summary' => [
                'sales_total_minor' => $total,
                'sales_total_formatted' => Money::format($total, $currency),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['cashier_name'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function salesByPaymentMethod(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = Sale::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('payment_method')
            ->orderByDesc(DB::raw('SUM(total)'))
            ->get([
                'payment_method',
                DB::raw('SUM(total) as sales_total_minor'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(function ($row) use ($currency) {
                $method = $row->payment_method instanceof PaymentMethod
                    ? $row->payment_method
                    : PaymentMethod::tryFrom((string) $row->payment_method);

                return [
                    'payment_method' => $method?->value ?? (string) $row->payment_method,
                    'payment_method_label' => $method?->label() ?? (string) $row->payment_method,
                    'sales_total_minor' => (int) $row->sales_total_minor,
                    'sales_total_formatted' => Money::format((int) $row->sales_total_minor, $currency),
                    'sales_count' => (int) $row->sales_count,
                ];
            })
            ->all();

        $total = array_sum(array_column($rows, 'sales_total_minor'));

        return [
            'summary' => [
                'sales_total_minor' => $total,
                'sales_total_formatted' => Money::format($total, $currency),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['payment_method_label'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    protected function productPerformance(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->orderByDesc(DB::raw('SUM(sale_items.line_total)'))
            ->limit(200)
            ->get([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue_minor'),
            ])
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'units_sold' => (int) $row->units_sold,
                'revenue_minor' => (int) $row->revenue_minor,
                'revenue_formatted' => Money::format((int) $row->revenue_minor, $currency),
            ])
            ->all();

        $total = array_sum(array_column($rows, 'revenue_minor'));

        return [
            'summary' => [
                'product_count' => count($rows),
                'revenue_minor' => $total,
                'revenue_formatted' => Money::format($total, $currency),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Products with stock on hand but zero or low sales in the period.
     *
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    protected function slowMovingProducts(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $soldProductIds = SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->whereNotNull('sale_items.product_id')
            ->distinct()
            ->pluck('sale_items.product_id')
            ->all();

        $balances = InventoryBalance::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('quantity', '>', 0)
            ->when($soldProductIds !== [], fn ($q) => $q->whereNotIn('product_id', $soldProductIds))
            ->with(['product:id,name,sku,cost_price', 'branch:id,name'])
            ->orderByDesc('quantity')
            ->limit(200)
            ->get();

        $rows = $balances->map(function (InventoryBalance $balance) use ($currency) {
            $value = $balance->inventoryValueMinor();

            return [
                'branch_name' => $balance->branch?->name,
                'product_name' => $balance->product?->name,
                'sku' => $balance->product?->sku,
                'quantity' => $balance->quantity,
                'value_minor' => $value,
                'value_formatted' => Money::format($value, $currency),
                'units_sold' => 0,
            ];
        })->all();

        return [
            'summary' => [
                'product_count' => count($rows),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    protected function inventoryValue(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = InventoryBalance::query()
            ->from('inventory_balances')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('branches', 'branches.id', '=', 'inventory_balances.branch_id')
            ->where('inventory_balances.business_id', $filter->business->id)
            ->whereIn('inventory_balances.branch_id', $filter->branchIds())
            ->groupBy('inventory_balances.branch_id', 'branches.name')
            ->orderBy('branches.name')
            ->get([
                'inventory_balances.branch_id',
                'branches.name as branch_name',
                DB::raw('SUM(inventory_balances.quantity) as units_on_hand'),
                DB::raw('COALESCE(SUM(inventory_balances.quantity * products.cost_price), 0) as value_minor'),
                DB::raw('COUNT(*) as sku_count'),
            ])
            ->map(fn ($row) => [
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name,
                'units_on_hand' => (int) $row->units_on_hand,
                'sku_count' => (int) $row->sku_count,
                'value_minor' => (int) $row->value_minor,
                'value_formatted' => Money::format((int) $row->value_minor, $currency),
            ])
            ->all();

        $total = array_sum(array_column($rows, 'value_minor'));

        return [
            'summary' => [
                'inventory_value_minor' => $total,
                'inventory_value_formatted' => Money::format($total, $currency),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['branch_name'],
                'value' => $row['value_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function stockMovementSummary(AnalyticsFilter $filter): array
    {
        $rows = StockMovement::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('type')
            ->orderBy('type')
            ->get([
                'type',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('COALESCE(SUM(CASE WHEN quantity_delta > 0 THEN quantity_delta ELSE 0 END), 0) as units_in'),
                DB::raw('COALESCE(SUM(CASE WHEN quantity_delta < 0 THEN ABS(quantity_delta) ELSE 0 END), 0) as units_out'),
            ])
            ->map(function ($row) {
                $type = $row->type instanceof StockMovementType
                    ? $row->type
                    : StockMovementType::tryFrom((string) $row->type);

                return [
                    'type' => $type?->value ?? (string) $row->type,
                    'type_label' => $type ? str_replace('_', ' ', $type->value) : (string) $row->type,
                    'movement_count' => (int) $row->movement_count,
                    'units_in' => (int) $row->units_in,
                    'units_out' => (int) $row->units_out,
                ];
            })
            ->all();

        return [
            'summary' => [
                'movement_count' => array_sum(array_column($rows, 'movement_count')),
                'units_in' => array_sum(array_column($rows, 'units_in')),
                'units_out' => array_sum(array_column($rows, 'units_out')),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['type_label'],
                'value' => $row['units_out'] + $row['units_in'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function expenseTrend(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();
        $periodExpr = DateGrouping::expression('expenses.expense_date', $filter->grain);

        $rows = Expense::query()
            ->from('expenses')
            ->where('business_id', $filter->business->id)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereDate('expense_date', '>=', $filter->dateFrom->toDateString())
            ->whereDate('expense_date', '<=', $filter->dateTo->toDateString())
            ->groupBy(DB::raw($periodExpr))
            ->orderBy(DB::raw($periodExpr))
            ->get([
                DB::raw("{$periodExpr} as period"),
                DB::raw('SUM(amount) as total_minor'),
                DB::raw('COUNT(*) as expense_count'),
            ])
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'total_minor' => (int) $row->total_minor,
                'total_formatted' => Money::format((int) $row->total_minor, $currency),
                'expense_count' => (int) $row->expense_count,
            ])
            ->all();

        $total = array_sum(array_column($rows, 'total_minor'));

        return [
            'summary' => [
                'expenses_total_minor' => $total,
                'expenses_total_formatted' => Money::format($total, $currency),
                'grain' => $filter->grain,
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['period'],
                'value' => $row['total_minor'],
            ], $rows),
        ];
    }

    /**
     * Gross profit estimate: revenue − (units × saved product cost_price).
     *
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    protected function grossProfit(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $rows = SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->orderByDesc(DB::raw('SUM(sale_items.line_total)'))
            ->limit(200)
            ->get([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue_minor'),
                DB::raw('COALESCE(SUM(sale_items.quantity * COALESCE(products.cost_price, 0)), 0) as cost_minor'),
            ])
            ->map(function ($row) use ($currency) {
                $revenue = (int) $row->revenue_minor;
                $cost = (int) $row->cost_minor;
                $profit = $revenue - $cost;

                return [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'sku' => $row->sku,
                    'units_sold' => (int) $row->units_sold,
                    'revenue_minor' => $revenue,
                    'revenue_formatted' => Money::format($revenue, $currency),
                    'cost_minor' => $cost,
                    'cost_formatted' => Money::format($cost, $currency),
                    'gross_profit_minor' => $profit,
                    'gross_profit_formatted' => Money::format($profit, $currency),
                ];
            })
            ->all();

        $revenue = array_sum(array_column($rows, 'revenue_minor'));
        $cost = array_sum(array_column($rows, 'cost_minor'));
        $profit = $revenue - $cost;

        return [
            'summary' => [
                'revenue_minor' => $revenue,
                'revenue_formatted' => Money::format($revenue, $currency),
                'cost_minor' => $cost,
                'cost_formatted' => Money::format($cost, $currency),
                'gross_profit_minor' => $profit,
                'gross_profit_formatted' => Money::format($profit, $currency),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function branchComparison(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();
        $branches = Branch::query()
            ->forBusiness($filter->business)
            ->whereIn('id', $filter->allowedBranchIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $salesByBranch = Sale::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->allowedBranchIds)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('branch_id')
            ->get([
                'branch_id',
                DB::raw('SUM(total) as sales_total_minor'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->keyBy('branch_id');

        $expensesByBranch = Expense::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->allowedBranchIds)
            ->whereDate('expense_date', '>=', $filter->dateFrom->toDateString())
            ->whereDate('expense_date', '<=', $filter->dateTo->toDateString())
            ->groupBy('branch_id')
            ->get([
                'branch_id',
                DB::raw('SUM(amount) as expenses_total_minor'),
            ])
            ->keyBy('branch_id');

        $inventoryByBranch = InventoryBalance::query()
            ->from('inventory_balances')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->where('inventory_balances.business_id', $filter->business->id)
            ->whereIn('inventory_balances.branch_id', $filter->allowedBranchIds)
            ->groupBy('inventory_balances.branch_id')
            ->get([
                'inventory_balances.branch_id',
                DB::raw('COALESCE(SUM(inventory_balances.quantity * products.cost_price), 0) as value_minor'),
            ])
            ->keyBy('branch_id');

        $rows = $branches->map(function (Branch $branch) use ($salesByBranch, $expensesByBranch, $inventoryByBranch, $currency) {
            $sales = (int) ($salesByBranch->get($branch->id)?->sales_total_minor ?? 0);
            $count = (int) ($salesByBranch->get($branch->id)?->sales_count ?? 0);
            $expenses = (int) ($expensesByBranch->get($branch->id)?->expenses_total_minor ?? 0);
            $inventory = (int) ($inventoryByBranch->get($branch->id)?->value_minor ?? 0);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'sales_total_minor' => $sales,
                'sales_total_formatted' => Money::format($sales, $currency),
                'sales_count' => $count,
                'expenses_total_minor' => $expenses,
                'expenses_total_formatted' => Money::format($expenses, $currency),
                'inventory_value_minor' => $inventory,
                'inventory_value_formatted' => Money::format($inventory, $currency),
            ];
        })->all();

        return [
            'summary' => [
                'branch_count' => count($rows),
                'sales_total_minor' => array_sum(array_column($rows, 'sales_total_minor')),
                'sales_total_formatted' => Money::format(
                    array_sum(array_column($rows, 'sales_total_minor')),
                    $currency,
                ),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['branch_name'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function shiftReport(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $shifts = StaffShift::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereBetween('clocked_in_at', [$filter->dateFrom, $filter->dateTo])
            ->with(['user:id,name', 'branch:id,name'])
            ->orderByDesc('clocked_in_at')
            ->get();

        $rows = $shifts->map(function (StaffShift $shift) use ($currency) {
            $sales = Sale::query()
                ->where('staff_shift_id', $shift->id)
                ->where('status', SaleStatus::Completed)
                ->selectRaw('COUNT(*) as sale_count')
                ->selectRaw('COALESCE(SUM(total), 0) as sales_total_minor')
                ->first();

            $cashSession = CashSession::query()
                ->where('staff_shift_id', $shift->id)
                ->first();

            return [
                'shift_id' => $shift->id,
                'cashier_name' => $shift->user?->name,
                'branch_name' => $shift->branch?->name,
                'clocked_in_at' => $shift->clocked_in_at?->toIso8601String(),
                'clocked_out_at' => $shift->clocked_out_at?->toIso8601String(),
                'status' => $shift->status->value,
                'sale_count' => (int) ($sales->sale_count ?? 0),
                'sales_total_minor' => (int) ($sales->sales_total_minor ?? 0),
                'sales_total_formatted' => Money::format((int) ($sales->sales_total_minor ?? 0), $currency),
                'opening_float_formatted' => $cashSession !== null
                    ? Money::format($cashSession->opening_float, $currency)
                    : '—',
                'variance_formatted' => $cashSession?->variance !== null
                    ? Money::format($cashSession->variance, $currency)
                    : '—',
                'has_variance' => $cashSession?->variance !== null && $cashSession->variance !== 0,
            ];
        })->all();

        $totalSales = array_sum(array_column($rows, 'sales_total_minor'));

        return [
            'summary' => [
                'shift_count' => count($rows),
                'sales_total_minor' => $totalSales,
                'sales_total_formatted' => Money::format($totalSales, $currency),
                'variance_count' => count(array_filter($rows, fn (array $r) => $r['has_variance'])),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => ($row['cashier_name'] ?? 'Unknown').' #'.$row['shift_id'],
                'value' => $row['sales_total_minor'],
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function endOfDay(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $sessions = CashSession::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$filter->dateFrom, $filter->dateTo])
            ->with(['user:id,name', 'branch:id,name'])
            ->orderByDesc('closed_at')
            ->get();

        $rows = $sessions->map(fn (CashSession $session) => [
            'session_id' => $session->id,
            'branch_name' => $session->branch?->name,
            'cashier_name' => $session->user?->name,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_float_formatted' => Money::format($session->opening_float, $currency),
            'expected_cash_formatted' => $session->expected_cash !== null
                ? Money::format($session->expected_cash, $currency)
                : '—',
            'counted_cash_formatted' => $session->counted_cash !== null
                ? Money::format($session->counted_cash, $currency)
                : '—',
            'variance_formatted' => $session->variance !== null
                ? Money::format($session->variance, $currency)
                : '—',
            'has_variance' => $session->variance !== null && $session->variance !== 0,
        ])->all();

        $salesTotal = (int) Sale::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->sum('total');

        return [
            'summary' => [
                'session_count' => count($rows),
                'sales_total_minor' => $salesTotal,
                'sales_total_formatted' => Money::format($salesTotal, $currency),
                'variance_count' => count(array_filter($rows, fn (array $r) => $r['has_variance'])),
                'total_variance_minor' => $sessions->sum('variance'),
                'total_variance_formatted' => Money::format((int) $sessions->sum('variance'), $currency),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => $row['branch_name'].' · '.$row['cashier_name'],
                'value' => 1,
            ], $rows),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, chart: list<array<string, mixed>>}
     */
    protected function cashReconciliation(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        $sessions = CashSession::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$filter->dateFrom, $filter->dateTo])
            ->with(['user:id,name', 'branch:id,name', 'varianceApprover:id,name'])
            ->orderByDesc('closed_at')
            ->get();

        $rows = $sessions->map(fn (CashSession $session) => [
            'session_id' => $session->id,
            'branch_name' => $session->branch?->name,
            'cashier_name' => $session->user?->name,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_float_minor' => $session->opening_float,
            'opening_float_formatted' => Money::format($session->opening_float, $currency),
            'expected_cash_minor' => $session->expected_cash,
            'expected_cash_formatted' => $session->expected_cash !== null
                ? Money::format($session->expected_cash, $currency)
                : '—',
            'counted_cash_minor' => $session->counted_cash,
            'counted_cash_formatted' => $session->counted_cash !== null
                ? Money::format($session->counted_cash, $currency)
                : '—',
            'closing_float_left_formatted' => $session->closing_float_left !== null
                ? Money::format($session->closing_float_left, $currency)
                : '—',
            'variance_minor' => $session->variance,
            'variance_formatted' => $session->variance !== null
                ? Money::format($session->variance, $currency)
                : '—',
            'variance_reason' => $session->variance_reason,
            'approved_by' => $session->varianceApprover?->name,
            'has_variance' => $session->variance !== null && $session->variance !== 0,
        ])->all();

        $varianceSessions = array_filter($rows, fn (array $r) => $r['has_variance']);

        return [
            'summary' => [
                'session_count' => count($rows),
                'variance_count' => count($varianceSessions),
                'total_variance_minor' => array_sum(array_column($rows, 'variance_minor')),
                'total_variance_formatted' => Money::format(
                    array_sum(array_map(fn (array $r) => (int) ($r['variance_minor'] ?? 0), $rows)),
                    $currency,
                ),
            ],
            'rows' => $rows,
            'chart' => array_map(fn (array $row) => [
                'label' => ($row['branch_name'] ?? '').' #'.$row['session_id'],
                'value' => abs((int) ($row['variance_minor'] ?? 0)),
            ], array_values($varianceSessions)),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    protected function auditLogs(AnalyticsFilter $filter): array
    {
        $perPage = 50;

        $paginator = AuditLog::query()
            ->forBusiness($filter->business)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = Collection::make($paginator->items())->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'user_name' => $log->user?->name,
            'auditable_type' => class_basename((string) $log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all();

        return [
            'summary' => [
                'total' => $paginator->total(),
            ],
            'rows' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return list<array{period: string, sales_total_minor: int, sales_total_formatted: string, sales_count: int}>
     */
    protected function salesGrouped(AnalyticsFilter $filter, string $grain): array
    {
        $currency = $filter->currency();
        $periodExpr = DateGrouping::expression('sales.created_at', $grain, $filter->timezone());

        return Sale::query()
            ->from('sales')
            ->where('business_id', $filter->business->id)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy(DB::raw($periodExpr))
            ->orderBy(DB::raw($periodExpr))
            ->get([
                DB::raw("{$periodExpr} as period"),
                DB::raw('SUM(total) as sales_total_minor'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'sales_total_minor' => (int) $row->sales_total_minor,
                'sales_total_formatted' => Money::format((int) $row->sales_total_minor, $currency),
                'sales_count' => (int) $row->sales_count,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function zeroMoneySummary(string $currency): array
    {
        return [
            'sales_total_minor' => 0,
            'sales_total_formatted' => Money::format(0, $currency),
            'sales_count' => 0,
        ];
    }
}
