<?php

namespace App\Enums;

use App\Contracts\FeatureFlagService;
use App\Models\Business;
use App\Support\FeatureFlags\Features;

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
    case ShiftReport = 'shift_report';
    case EndOfDay = 'end_of_day';
    case CashReconciliation = 'cash_reconciliation';
    case ProductPerformance = 'product_performance';
    case SlowMovingProducts = 'slow_moving_products';
    case InventoryValue = 'inventory_value';
    case StockMovementSummary = 'stock_movement_summary';
    case ExpenseTrend = 'expense_trend';
    case GrossProfit = 'gross_profit';
    case NegotiationPerformance = 'negotiation_performance';

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
            self::BranchComparison => Features::CONSOLIDATED_REPORTS,
            self::AuditLogs => Features::AUDIT_LOGS,
            default => Features::ADVANCED_REPORTS,
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

    public function isAvailableFor(Business $business, FeatureFlagService $features): bool
    {
        $feature = $this->feature();

        return $feature === null || $features->hasFeature($business, $feature);
    }

    public function group(): string
    {
        return match ($this) {
            self::SalesSummary,
            self::SalesTrends,
            self::SalesByBranch,
            self::SalesByCashier,
            self::SalesByPaymentMethod,
            self::ShiftReport,
            self::EndOfDay,
            self::CashReconciliation,
            self::ProductPerformance,
            self::SlowMovingProducts,
            self::GrossProfit,
            self::NegotiationPerformance,
            self::BranchComparison => 'sales',
            self::ExpensesSummary,
            self::ExpenseTrend => 'expenses',
            self::LowStock,
            self::InventoryValue,
            self::StockMovementSummary => 'inventory',
            self::AuditLogs => 'compliance',
        };
    }

    public function supportsPdfExport(): bool
    {
        return $this->supportsCsvExport();
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
            self::ShiftReport => 'Shift report',
            self::EndOfDay => 'End of day report',
            self::CashReconciliation => 'Cash reconciliation',
            self::ProductPerformance => 'Product performance',
            self::SlowMovingProducts => 'Slow-moving products',
            self::InventoryValue => 'Inventory value',
            self::StockMovementSummary => 'Stock movement summary',
            self::ExpenseTrend => 'Expense trend',
            self::GrossProfit => 'Gross profit estimate',
            self::NegotiationPerformance => 'Negotiation performance',
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
            self::ShiftReport => 'Sales and cash totals per staff shift.',
            self::EndOfDay => 'Daily branch totals including cash sessions and payment mix.',
            self::CashReconciliation => 'Opening float, expected cash, counted cash, and variances.',
            self::ProductPerformance => 'Units sold and revenue by product.',
            self::SlowMovingProducts => 'Products with little or no recent sales.',
            self::InventoryValue => 'Stock value at cost across branches.',
            self::StockMovementSummary => 'Inbound and outbound stock movements by type.',
            self::ExpenseTrend => 'Expense totals over time.',
            self::GrossProfit => 'Estimated profit using saved product cost prices.',
            self::NegotiationPerformance => 'Average selling price, margin, negotiation frequency, value, and negotiation score by salesperson.',
            self::BranchComparison => 'Consolidated side-by-side metrics for all branches.',
            self::AuditLogs => 'Review sensitive actions across your business.',
        };
    }
}
