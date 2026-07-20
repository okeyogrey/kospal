import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Package,
    Receipt,
    ShoppingBag,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { AnalyticsAreaChart } from '@/components/analytics/area-chart';
import { AnalyticsPieChart } from '@/components/analytics/pie-chart';
import { AnalyticsFilters } from '@/components/analytics/report-filters';
import { MetricCard } from '@/components/analytics/metric-card';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { compactChartMoney, formatChartMoney } from '@/lib/chart-money';
import { dashboard } from '@/routes';
import { index as reportsIndex, show as reportShow } from '@/routes/reports';
import { show as saleShow } from '@/routes/sales';

type Metrics = {
    today_sales_formatted: string;
    week_sales_formatted: string;
    month_sales_formatted: string;
    sales_count: number;
    average_order_value_formatted: string;
    low_stock_count: number;
    expenses_formatted: string;
    losses_formatted: string;
    losses_units: number;
    gross_profit_formatted: string;
    net_profit_formatted: string;
    period_revenue_formatted: string;
};

type RecentSale = {
    id: number;
    sale_number: string;
    branch_name: string | null;
    cashier_name: string | null;
    total_formatted: string;
    created_at: string | null;
};

type TopProduct = {
    product_id: number | null;
    name: string;
    sku: string | null;
    units_sold: number;
    revenue_formatted: string;
    revenue_minor?: number;
};

type ChartPoint = { label: string; value: number };

type BranchOption = { id: number; name: string };

type NegotiationRank = {
    rank: number;
    cashier_id: number | null;
    cashier_name: string;
    negotiation_score: number;
    average_selling_price_formatted: string;
    average_margin_percent: number;
    negotiation_frequency_percent: number;
    negotiation_value_formatted: string;
};

export default function Dashboard({
    metrics,
    recent_sales,
    top_products,
    sales_trend,
    payment_breakdown,
    loss_breakdown,
    negotiation_ranking = [],
    branches,
    filters,
    currency,
    permissions,
}: {
    metrics: Metrics;
    recent_sales: RecentSale[];
    top_products: TopProduct[];
    sales_trend: ChartPoint[];
    payment_breakdown: ChartPoint[];
    loss_breakdown: ChartPoint[];
    negotiation_ranking?: NegotiationRank[];
    branches: BranchOption[];
    filters: {
        branch_id: number | null;
        date_from: string;
        date_to: string;
    };
    currency: string;
    permissions: { view_reports: boolean };
}) {
    const { t } = useTranslations();
    const hasActivity =
        metrics.sales_count > 0 ||
        metrics.low_stock_count > 0 ||
        metrics.losses_units > 0 ||
        recent_sales.length > 0 ||
        sales_trend.length > 0 ||
        loss_breakdown.some((point) => point.value > 0);

    const maxUnits = Math.max(...top_products.map((p) => p.units_sold), 1);

    return (
        <>
            <Head title={t('pages.dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {t('pages.dashboard.title')}
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {t('pages.dashboard.description')}
                        </p>
                    </div>
                    {permissions.view_reports ? (
                        <Button variant="outline" asChild>
                            <Link href={reportsIndex()}>
                                {t('pages.dashboard.view_reports')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <AnalyticsFilters
                    actionUrl={dashboard.url()}
                    branches={branches}
                    filters={filters}
                    submitLabel={t('pages.dashboard.apply_filters')}
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <MetricCard
                        label={t('pages.dashboard.metrics.today')}
                        value={metrics.today_sales_formatted}
                        icon={<Wallet className="size-4" />}
                    />
                    <MetricCard
                        label={t('pages.dashboard.metrics.week')}
                        value={metrics.week_sales_formatted}
                        icon={<TrendingUp className="size-4" />}
                        accent="accent"
                    />
                    <MetricCard
                        label={t('pages.dashboard.metrics.month')}
                        value={metrics.month_sales_formatted}
                        icon={<ShoppingBag className="size-4" />}
                    />
                    <MetricCard
                        label={t('pages.dashboard.metrics.sales_count')}
                        value={metrics.sales_count}
                        hint={t('pages.dashboard.metrics.sales_count_hint')}
                        icon={<Receipt className="size-4" />}
                        accent="muted"
                    />
                    <MetricCard
                        label={t('pages.dashboard.metrics.aov')}
                        value={metrics.average_order_value_formatted}
                        hint={t('pages.dashboard.metrics.aov_hint')}
                        icon={<Package className="size-4" />}
                        accent="accent"
                    />
                    <MetricCard
                        label={t('pages.dashboard.metrics.low_stock')}
                        value={
                            <span className="inline-flex items-center gap-2">
                                {metrics.low_stock_count}
                                {metrics.low_stock_count > 0 ? (
                                    <AlertTriangle className="size-5 text-amber-600" />
                                ) : null}
                            </span>
                        }
                        icon={<AlertTriangle className="size-4" />}
                        accent={
                            metrics.low_stock_count > 0 ? 'warning' : 'muted'
                        }
                    />
                    <MetricCard
                        label="Period revenue"
                        value={metrics.period_revenue_formatted}
                        icon={<Wallet className="size-4" />}
                        accent="accent"
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Expenses"
                        value={metrics.expenses_formatted}
                        icon={<Receipt className="size-4" />}
                        accent="muted"
                    />
                    <MetricCard
                        label="Losses"
                        value={
                            <span className="inline-flex items-center gap-2">
                                {metrics.losses_formatted}
                                {metrics.losses_units > 0 ? (
                                    <span className="text-sm font-normal text-muted-foreground">
                                        ({metrics.losses_units} units)
                                    </span>
                                ) : null}
                            </span>
                        }
                        icon={<TrendingDown className="size-4" />}
                        accent={metrics.losses_units > 0 ? 'warning' : 'muted'}
                    />
                    <MetricCard
                        label="Gross profit"
                        value={metrics.gross_profit_formatted}
                        icon={<TrendingUp className="size-4" />}
                        accent="accent"
                    />
                    <MetricCard
                        label="Net profit"
                        value={metrics.net_profit_formatted}
                        icon={<TrendingUp className="size-4" />}
                    />
                </div>

                {!hasActivity ? (
                    <div className="rounded-2xl border border-border/80 bg-card/80 shadow-sm">
                        <EmptyState
                            title={t('pages.dashboard.empty_title')}
                            description={t('pages.dashboard.empty_description')}
                        />
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm">
                                <div className="mb-4 flex items-center gap-2">
                                    <div className="flex size-8 items-center justify-center rounded-xl bg-secondary text-primary">
                                        <TrendingUp className="size-4" />
                                    </div>
                                    <h2 className="font-display text-lg font-semibold">
                                        {t('pages.dashboard.sales_trend')}
                                    </h2>
                                </div>
                                <AnalyticsAreaChart
                                    data={sales_trend}
                                    emptyLabel={t(
                                        'pages.dashboard.no_recent_sales',
                                    )}
                                    valueFormatter={(value) =>
                                        compactChartMoney(value, currency)
                                    }
                                    tooltipFormatter={(value) =>
                                        formatChartMoney(value, currency)
                                    }
                                />
                            </section>

                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm">
                                <div className="mb-4 flex items-center gap-2">
                                    <div className="flex size-8 items-center justify-center rounded-xl bg-secondary text-primary">
                                        <Wallet className="size-4" />
                                    </div>
                                    <h2 className="font-display text-lg font-semibold">
                                        {t('pages.dashboard.payment_mix')}
                                    </h2>
                                </div>
                                <AnalyticsPieChart
                                    data={payment_breakdown}
                                    emptyLabel={t(
                                        'pages.dashboard.no_recent_sales',
                                    )}
                                    valueFormatter={(value) =>
                                        formatChartMoney(value, currency)
                                    }
                                />
                            </section>
                        </div>

                        {loss_breakdown.some((point) => point.value > 0) ? (
                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm sm:max-w-xl">
                                <div className="mb-4 flex items-center gap-2">
                                    <div className="flex size-8 items-center justify-center rounded-xl bg-secondary text-primary">
                                        <TrendingDown className="size-4" />
                                    </div>
                                    <h2 className="font-display text-lg font-semibold">
                                        Loss breakdown
                                    </h2>
                                </div>
                                <AnalyticsPieChart
                                    data={loss_breakdown}
                                    valueFormatter={(value) =>
                                        formatChartMoney(value, currency)
                                    }
                                />
                            </section>
                        ) : null}

                        {permissions.view_reports &&
                        negotiation_ranking.length > 0 ? (
                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm">
                                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="font-display text-lg font-semibold">
                                        Negotiation ranking
                                    </h2>
                                    <Link
                                        href={reportShow(
                                            'negotiation_performance',
                                        )}
                                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                    >
                                        Full report
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                </div>
                                <ul className="divide-y divide-border/70">
                                    {negotiation_ranking.map((row) => (
                                        <li
                                            key={row.cashier_id ?? row.cashier_name}
                                            className="flex items-center justify-between gap-3 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    #{row.rank}{' '}
                                                    {row.cashier_name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    Avg{' '}
                                                    {
                                                        row.average_selling_price_formatted
                                                    }{' '}
                                                    · Margin{' '}
                                                    {row.average_margin_percent}
                                                    % · Negotiated{' '}
                                                    {
                                                        row.negotiation_frequency_percent
                                                    }
                                                    %
                                                </p>
                                            </div>
                                            <Badge variant="secondary" className="tabular-nums">
                                                {row.negotiation_score}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ) : null}

                        <div className="grid gap-4 lg:grid-cols-2">
                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm">
                                <h2 className="font-display text-lg font-semibold">
                                    {t('pages.dashboard.recent_sales')}
                                </h2>
                                {recent_sales.length === 0 ? (
                                    <p className="mt-4 text-sm text-muted-foreground">
                                        {t('pages.dashboard.no_recent_sales')}
                                    </p>
                                ) : (
                                    <ul className="mt-4 divide-y divide-border/70">
                                        {recent_sales.map((sale) => (
                                            <li key={sale.id} className="py-3">
                                                <Link
                                                    href={saleShow(sale.id)}
                                                    className="flex items-start justify-between gap-3 hover:opacity-90"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {sale.sale_number}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {[
                                                                sale.branch_name,
                                                                sale.cashier_name,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    </div>
                                                    <p className="shrink-0 tabular-nums">
                                                        {sale.total_formatted}
                                                    </p>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm">
                                <h2 className="mb-4 font-display text-lg font-semibold">
                                    {t('pages.dashboard.top_products')}
                                </h2>
                                {top_products.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('pages.dashboard.no_top_products')}
                                    </p>
                                ) : (
                                    <ul className="space-y-4">
                                        {top_products.map((product) => (
                                            <li
                                                key={`${product.product_id}-${product.sku}`}
                                                className="space-y-1.5"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {product.name}
                                                        </p>
                                                        <div className="mt-1 flex flex-wrap items-center gap-2">
                                                            {product.sku ? (
                                                                <Badge variant="secondary">
                                                                    {
                                                                        product.sku
                                                                    }
                                                                </Badge>
                                                            ) : null}
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    product.units_sold
                                                                }{' '}
                                                                {t(
                                                                    'pages.dashboard.units',
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <p className="shrink-0 tabular-nums">
                                                        {
                                                            product.revenue_formatted
                                                        }
                                                    </p>
                                                </div>
                                                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-primary/80 transition-all"
                                                        style={{
                                                            width: `${Math.max(6, Math.round((product.units_sold / maxUnits) * 100))}%`,
                                                        }}
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
