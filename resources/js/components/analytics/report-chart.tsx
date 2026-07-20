import { ChartColumn, ChartPie, TrendingUp } from 'lucide-react';
import { AnalyticsAreaChart } from '@/components/analytics/area-chart';
import { AnalyticsBarChart } from '@/components/analytics/bar-chart';
import { AnalyticsPieChart } from '@/components/analytics/pie-chart';
import type { ChartPoint } from '@/components/analytics/chart-theme';
import { compactChartMoney, formatChartMoney } from '@/lib/chart-money';
import { cn } from '@/lib/utils';

export type ReportChartKind = 'area' | 'bar' | 'horizontal-bar' | 'pie';

const PIE_REPORTS = new Set([
    'expenses_summary',
    'sales_by_payment_method',
    'stock_movement_summary',
]);

const AREA_REPORTS = new Set([
    'sales_summary',
    'sales_trends',
    'expense_trend',
]);

const HORIZONTAL_BAR_REPORTS = new Set([
    'sales_by_cashier',
    'product_performance',
    'gross_profit',
    'negotiation_performance',
]);

const COUNT_REPORTS = new Set([
    'stock_movement_summary',
    'negotiation_performance',
]);

export function resolveReportChartKind(reportKey: string): ReportChartKind {
    if (PIE_REPORTS.has(reportKey)) {
        return 'pie';
    }

    if (AREA_REPORTS.has(reportKey)) {
        return 'area';
    }

    if (HORIZONTAL_BAR_REPORTS.has(reportKey)) {
        return 'horizontal-bar';
    }

    return 'bar';
}

export function ReportChartPanel({
    kind,
    data,
    emptyLabel,
    title,
    currency,
    reportKey,
    className,
}: {
    kind: ReportChartKind;
    data: ChartPoint[];
    emptyLabel: string;
    title?: string;
    currency?: string;
    reportKey?: string;
    className?: string;
}) {
    const Icon =
        kind === 'pie'
            ? ChartPie
            : kind === 'area'
              ? TrendingUp
              : ChartColumn;

    const isMoney = !COUNT_REPORTS.has(reportKey ?? '');
    const tickFormatter =
        isMoney && currency
            ? (value: number) => compactChartMoney(value, currency)
            : undefined;
    const tooltipFormatter =
        isMoney && currency
            ? (value: number) => formatChartMoney(value, currency)
            : undefined;

    return (
        <section
            className={cn(
                'rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm',
                className,
            )}
        >
            {title ? (
                <div className="mb-4 flex items-center gap-2">
                    <div className="flex size-8 items-center justify-center rounded-xl bg-secondary text-primary">
                        <Icon className="size-4" />
                    </div>
                    <h2 className="font-display text-lg font-semibold">
                        {title}
                    </h2>
                </div>
            ) : null}

            {kind === 'pie' ? (
                <AnalyticsPieChart
                    data={data}
                    emptyLabel={emptyLabel}
                    valueFormatter={tooltipFormatter}
                />
            ) : null}
            {kind === 'area' ? (
                <AnalyticsAreaChart
                    data={data}
                    emptyLabel={emptyLabel}
                    valueFormatter={tickFormatter}
                    tooltipFormatter={tooltipFormatter}
                />
            ) : null}
            {kind === 'bar' ? (
                <AnalyticsBarChart
                    data={data}
                    emptyLabel={emptyLabel}
                    valueFormatter={tickFormatter}
                    tooltipFormatter={tooltipFormatter}
                />
            ) : null}
            {kind === 'horizontal-bar' ? (
                <AnalyticsBarChart
                    data={data}
                    emptyLabel={emptyLabel}
                    horizontal
                    valueFormatter={tickFormatter}
                    tooltipFormatter={tooltipFormatter}
                />
            ) : null}
        </section>
    );
}
