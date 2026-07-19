<?php

namespace App\Services\Analytics;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\DateGrouping;
use App\Support\Money\Money;
use App\Support\Time\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardMetricsService
{
    /**
     * @return array{
     *     metrics: array<string, mixed>,
     *     recent_sales: list<array<string, mixed>>,
     *     top_products: list<array<string, mixed>>,
     *     sales_trend: list<array{label: string, value: int}>,
     *     payment_breakdown: list<array{label: string, value: int}>,
     *     loss_breakdown: list<array{label: string, value: int}>,
     * }
     */
    public function build(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();
        $branchIds = $filter->branchIds();

        if ($branchIds === []) {
            return [
                'metrics' => $this->emptyMetrics($currency),
                'recent_sales' => [],
                'top_products' => [],
                'sales_trend' => [],
                'payment_breakdown' => [],
                'loss_breakdown' => [],
            ];
        }

        $timezone = $filter->timezone();
        $nowLocal = BusinessClock::now($timezone);
        $todayStart = $nowLocal->startOfDay()->utc();
        $todayEnd = $nowLocal->endOfDay()->utc();
        $weekStart = BusinessClock::startOfWeekUtc($nowLocal, $timezone);
        $monthStart = BusinessClock::startOfMonthUtc($nowLocal, $timezone);

        $today = $this->salesAggregate($filter->business->id, $branchIds, $todayStart, $todayEnd);
        $week = $this->salesAggregate($filter->business->id, $branchIds, $weekStart, $todayEnd);
        $month = $this->salesAggregate($filter->business->id, $branchIds, $monthStart, $todayEnd);

        $period = $this->salesAggregate(
            $filter->business->id,
            $branchIds,
            $filter->dateFrom,
            $filter->dateTo,
        );

        $cogs = $this->cogsAggregate($filter);
        $expenses = $this->expensesAggregate($filter);
        $losses = $this->lossesAggregate($filter);
        $grossProfit = $period['total'] - $cogs;
        $netProfit = $grossProfit - $expenses - $losses['total_minor'];

        $lowStockCount = InventoryBalance::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $branchIds)
            ->lowStock()
            ->count();

        $avgOrderValue = $period['count'] > 0
            ? (int) intdiv($period['total'], $period['count'])
            : 0;

        return [
            'metrics' => [
                'today_sales_minor' => $today['total'],
                'today_sales_formatted' => Money::format($today['total'], $currency),
                'week_sales_minor' => $week['total'],
                'week_sales_formatted' => Money::format($week['total'], $currency),
                'month_sales_minor' => $month['total'],
                'month_sales_formatted' => Money::format($month['total'], $currency),
                'sales_count' => $period['count'],
                'average_order_value_minor' => $avgOrderValue,
                'average_order_value_formatted' => Money::format($avgOrderValue, $currency),
                'low_stock_count' => $lowStockCount,
                'period_revenue_minor' => $period['total'],
                'period_revenue_formatted' => Money::format($period['total'], $currency),
                'cogs_minor' => $cogs,
                'cogs_formatted' => Money::format($cogs, $currency),
                'gross_profit_minor' => $grossProfit,
                'gross_profit_formatted' => Money::format($grossProfit, $currency),
                'expenses_minor' => $expenses,
                'expenses_formatted' => Money::format($expenses, $currency),
                'losses_minor' => $losses['total_minor'],
                'losses_formatted' => Money::format($losses['total_minor'], $currency),
                'losses_units' => $losses['units'],
                'net_profit_minor' => $netProfit,
                'net_profit_formatted' => Money::format($netProfit, $currency),
            ],
            'recent_sales' => $this->recentSales($filter, $currency),
            'top_products' => $this->topProducts($filter, $currency),
            'sales_trend' => $this->salesTrend($filter),
            'payment_breakdown' => $this->paymentBreakdown($filter),
            'loss_breakdown' => $losses['breakdown'],
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{total: int, count: int}
     */
    protected function salesAggregate(
        int $businessId,
        array $branchIds,
        mixed $from,
        mixed $to,
    ): array {
        $row = Sale::query()
            ->where('business_id', $businessId)
            ->whereIn('branch_id', $branchIds)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total, COUNT(*) as sales_count')
            ->first();

        return [
            'total' => (int) ($row->sales_total ?? 0),
            'count' => (int) ($row->sales_count ?? 0),
        ];
    }

    protected function cogsAggregate(AnalyticsFilter $filter): int
    {
        return (int) SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->selectRaw('COALESCE(SUM(sale_items.quantity * COALESCE(products.cost_price, 0)), 0) as cogs')
            ->value('cogs');
    }

    protected function expensesAggregate(AnalyticsFilter $filter): int
    {
        return (int) Expense::query()
            ->where('business_id', $filter->business->id)
            ->whereIn('branch_id', $filter->branchIds())
            ->whereDate('expense_date', '>=', $filter->dateFrom->toDateString())
            ->whereDate('expense_date', '<=', $filter->dateTo->toDateString())
            ->sum('amount');
    }

    /**
     * @return array{total_minor: int, units: int, breakdown: list<array{label: string, value: int}>}
     */
    protected function lossesAggregate(AnalyticsFilter $filter): array
    {
        $reasons = array_map(
            fn (StockAdjustmentReason $reason) => $reason->value,
            StockAdjustmentReason::lossReasons(),
        );

        $rows = StockMovement::query()
            ->from('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.business_id', $filter->business->id)
            ->whereIn('stock_movements.branch_id', $filter->branchIds())
            ->where('stock_movements.type', StockMovementType::Adjustment->value)
            ->whereIn('stock_movements.reason', $reasons)
            ->where('stock_movements.quantity_delta', '<', 0)
            ->whereBetween('stock_movements.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('stock_movements.reason')
            ->get([
                'stock_movements.reason',
                DB::raw('SUM(ABS(stock_movements.quantity_delta)) as units'),
                DB::raw('SUM(ABS(stock_movements.quantity_delta) * products.cost_price) as loss_minor'),
            ]);

        $breakdown = [];
        $totalMinor = 0;
        $units = 0;

        foreach ($rows as $row) {
            $reason = StockAdjustmentReason::tryFrom((string) $row->reason);
            $value = (int) $row->loss_minor;
            $totalMinor += $value;
            $units += (int) $row->units;
            $breakdown[] = [
                'label' => $reason?->label() ?? Str::title((string) $row->reason),
                'value' => $value,
            ];
        }

        return [
            'total_minor' => $totalMinor,
            'units' => $units,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentSales(AnalyticsFilter $filter, string $currency): array
    {
        return Sale::query()
            ->forBusiness($filter->business)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->with(['branch:id,name', 'cashier:id,name'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'branch_id', 'cashier_id', 'sale_number', 'total', 'currency', 'payment_method', 'created_at'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'branch_name' => $sale->branch?->name,
                'cashier_name' => $sale->cashier?->name,
                'total_minor' => $sale->total,
                'total_formatted' => Money::format($sale->total, $currency),
                'payment_method' => $sale->payment_method?->value,
                'created_at' => $sale->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function topProducts(AnalyticsFilter $filter, string $currency): array
    {
        return SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity)'))
            ->limit(5)
            ->get([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue_minor'),
            ])
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->product_name,
                'sku' => $row->sku,
                'units_sold' => (int) $row->units_sold,
                'revenue_minor' => (int) $row->revenue_minor,
                'revenue_formatted' => Money::format((int) $row->revenue_minor, $currency),
            ])
            ->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    protected function salesTrend(AnalyticsFilter $filter): array
    {
        $periodExpr = DateGrouping::expression('sales.created_at', 'day', $filter->timezone());

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
            ])
            ->map(fn ($row) => [
                'label' => (string) $row->period,
                'value' => (int) $row->sales_total_minor,
            ])
            ->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    protected function paymentBreakdown(AnalyticsFilter $filter): array
    {
        return Sale::query()
            ->where('business_id', $filter->business->id)
            ->whereIn('branch_id', $filter->branchIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('payment_method')
            ->orderByDesc(DB::raw('SUM(total)'))
            ->get([
                'payment_method',
                DB::raw('SUM(total) as sales_total_minor'),
            ])
            ->map(function ($row) {
                $method = $row->payment_method;

                return [
                    'label' => $method instanceof PaymentMethod
                        ? $method->label()
                        : Str::of((string) $method)->replace('_', ' ')->title()->toString(),
                    'value' => (int) $row->sales_total_minor,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyMetrics(string $currency): array
    {
        $zero = Money::format(0, $currency);

        return [
            'today_sales_minor' => 0,
            'today_sales_formatted' => $zero,
            'week_sales_minor' => 0,
            'week_sales_formatted' => $zero,
            'month_sales_minor' => 0,
            'month_sales_formatted' => $zero,
            'sales_count' => 0,
            'average_order_value_minor' => 0,
            'average_order_value_formatted' => $zero,
            'low_stock_count' => 0,
            'period_revenue_minor' => 0,
            'period_revenue_formatted' => $zero,
            'cogs_minor' => 0,
            'cogs_formatted' => $zero,
            'gross_profit_minor' => 0,
            'gross_profit_formatted' => $zero,
            'expenses_minor' => 0,
            'expenses_formatted' => $zero,
            'losses_minor' => 0,
            'losses_formatted' => $zero,
            'losses_units' => 0,
            'net_profit_minor' => 0,
            'net_profit_formatted' => $zero,
        ];
    }
}
