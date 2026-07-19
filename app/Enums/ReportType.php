<?php

namespace App\Enums;

enum ReportType: string
{
    // Starter (core)
    case SalesSummary = 'sales_summary';
    case ExpensesSummary = 'expenses_summary';
    case LowStock = 'low_stock';

    // Pro / Enterprise (advanced_reports)
    case SalesTrends = 'sales_trends';
    case SalesByBranch = 'sales_by_branch';
    case SalesByCashier = 'sales_by_cashier';
    case SalesByPaymentMethod = 'sales_by_payment_method';
    case ProductPerformance = 'product_performance';
    case SlowMovingProducts = 'slow_moving_products';
    case InventoryValue = 'inventory_value';
    case StockMovementSummary = 'stock_movement_summary';
    case ExpenseTrend = 'expense_trend';
    case GrossProfit = 'gross_profit';

    // Enterprise
    case BranchComparison = 'branch_comparison';
    case AuditLogs = 'audit_logs';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Required plan feature, or null when available on all plans (core).
     */
    public function feature(): ?string
    {
        return match ($this) {
            self::SalesSummary,
            self::ExpensesSummary,
            self::LowStock => null,
            self::BranchComparison => 'consolidated_reports',
            self::AuditLogs => 'audit_logs',
            default => 'advanced_reports',
        };
    }

    public function minPlan(): Plan
    {
        return match ($this) {
            self::SalesSummary,
            self::ExpensesSummary,
            self::LowStock => Plan::Starter,
            self::BranchComparison,
            self::AuditLogs => Plan::Enterprise,
            default => Plan::Pro,
        };
    }

    public function isAvailableOn(Plan $plan): bool
    {
        $feature = $this->feature();

        return $feature === null || $plan->hasFeature($feature);
    }

    public function group(): string
    {
        return match ($this) {
            self::SalesSummary,
            self::SalesTrends,
            self::SalesByBranch,
            self::SalesByCashier,
            self::SalesByPaymentMethod,
            self::ProductPerformance,
            self::SlowMovingProducts,
            self::GrossProfit,
            self::BranchComparison => 'sales',
            self::ExpensesSummary,
            self::ExpenseTrend => 'expenses',
            self::LowStock,
            self::InventoryValue,
            self::StockMovementSummary => 'inventory',
            self::AuditLogs => 'compliance',
        };
    }

    public function supportsCsvExport(): bool
    {
        return $this !== self::AuditLogs;
    }

    public function label(): string
    {
        return match ($this) {
            self::SalesSummary => 'Sales summary',
            self::ExpensesSummary => 'Expenses summary',
            self::LowStock => 'Low stock',
            self::SalesTrends => 'Sales trends',
            self::SalesByBranch => 'Sales by branch',
            self::SalesByCashier => 'Sales by cashier',
            self::SalesByPaymentMethod => 'Sales by payment method',
            self::ProductPerformance => 'Product performance',
            self::SlowMovingProducts => 'Slow-moving products',
            self::InventoryValue => 'Inventory value',
            self::StockMovementSummary => 'Stock movement summary',
            self::ExpenseTrend => 'Expense trend',
            self::GrossProfit => 'Gross profit estimate',
            self::BranchComparison => 'Multi-branch comparison',
            self::AuditLogs => 'Audit log',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SalesSummary => 'Totals, counts, and average order value for a period.',
            self::ExpensesSummary => 'Spending totals grouped by category.',
            self::LowStock => 'Products at or below their reorder level.',
            self::SalesTrends => 'Sales over time by day, week, or month.',
            self::SalesByBranch => 'Compare sales performance across branches.',
            self::SalesByCashier => 'Sales totals attributed to each cashier.',
            self::SalesByPaymentMethod => 'Breakdown of sales by payment method.',
            self::ProductPerformance => 'Units sold and revenue by product.',
            self::SlowMovingProducts => 'Products with little or no recent sales.',
            self::InventoryValue => 'Stock value at cost across branches.',
            self::StockMovementSummary => 'Inbound and outbound stock movements by type.',
            self::ExpenseTrend => 'Expense totals over time.',
            self::GrossProfit => 'Estimated profit using saved product cost prices.',
            self::BranchComparison => 'Consolidated side-by-side metrics for all branches.',
            self::AuditLogs => 'Review sensitive actions across your business.',
        };
    }
}
