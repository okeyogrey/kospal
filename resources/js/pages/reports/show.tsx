import { Head, Link } from '@inertiajs/react';
import { Download, Lock } from 'lucide-react';
import { AnalyticsFilters } from '@/components/analytics/report-filters';
import {
    ReportChartPanel,
    resolveReportChartKind,
} from '@/components/analytics/report-chart';
import { MetricCard } from '@/components/analytics/metric-card';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import {
    exportMethod as reportExport,
    index as reportsIndex,
    show as reportShow,
} from '@/routes/reports';
import { index as subscriptionIndex } from '@/routes/subscription';

type ReportMeta = {
    key: string;
    label: string;
    description: string;
    group: string;
    min_plan: string;
    available: boolean;
    supports_csv: boolean;
    supports_grain: boolean;
};

type BranchOption = { id: number; name: string };

function humanizeKey(key: string): string {
    return key
        .replace(/_formatted$/, '')
        .replace(/_minor$/, '')
        .replaceAll('_', ' ');
}

export default function ReportShow({
    report,
    summary,
    rows,
    chart,
    branches,
    filters,
    currency,
    permissions,
    upgrade,
}: {
    report: ReportMeta;
    summary: Record<string, string | number | null>;
    rows: Array<Record<string, string | number | null | boolean>>;
    chart: Array<{ label: string; value: number }>;
    meta: { page: number; per_page: number; total: number } | null;
    branches: BranchOption[];
    filters: {
        branch_id: number | null;
        date_from: string;
        date_to: string;
        grain: string;
        include_voided: boolean;
    };
    currency: string;
    permissions: {
        export: boolean;
        enhanced_exports: boolean;
    };
    upgrade: {
        required_plan: string;
        current_plan: string;
        message: string;
    } | null;
}) {
    const { t } = useTranslations();
    const columns = rows.length > 0 ? Object.keys(rows[0]) : [];
    const displayColumns = columns.filter(
        (column) =>
            !column.endsWith('_minor') &&
            column !== 'product_id' &&
            column !== 'category_id' &&
            column !== 'branch_id' &&
            column !== 'cashier_id',
    );

    const summaryCards = Object.entries(summary).filter(
        ([key]) => key.endsWith('_formatted') || !key.endsWith('_minor'),
    );

    const chartKind = resolveReportChartKind(report.key);
    const derivedChart =
        chart.length > 0 ? chart : deriveChartFromRows(report.key, rows);

    const exportUrl = reportExport.url(report.key, {
        query: {
            branch_id: filters.branch_id ?? undefined,
            date_from: filters.date_from,
            date_to: filters.date_to,
            grain: filters.grain,
            include_voided: filters.include_voided ? '1' : undefined,
        },
    });

    return (
        <>
            <Head title={report.label} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{report.group}</Badge>
                            {!report.available ? (
                                <Badge variant="outline">
                                    {report.min_plan}+
                                </Badge>
                            ) : null}
                        </div>
                        <h1 className="font-display text-2xl font-semibold">
                            {report.label}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {report.description}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={reportsIndex()}>
                                {t('pages.reports.back')}
                            </Link>
                        </Button>
                        {permissions.export && report.available ? (
                            <Button asChild>
                                <a href={exportUrl}>
                                    <Download className="size-4" />
                                    {t('pages.reports.export_csv')}
                                </a>
                            </Button>
                        ) : null}
                    </div>
                </div>

                {!report.available && upgrade ? (
                    <div className="rounded-2xl border border-border/80 bg-card/80 p-8 text-center shadow-sm">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary">
                            <Lock className="size-5" />
                        </div>
                        <h2 className="mt-4 font-display text-xl font-semibold">
                            {t('pages.reports.upgrade_title')}
                        </h2>
                        <p className="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">
                            {upgrade.message}
                        </p>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t('pages.reports.current_plan')}:{' '}
                            <span className="font-medium">
                                {upgrade.current_plan}
                            </span>
                        </p>
                        <Button asChild className="mt-5">
                            <Link href={subscriptionIndex()}>
                                {t('pages.reports.upgrade_cta')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <AnalyticsFilters
                            actionUrl={reportShow.url(report.key)}
                            branches={branches}
                            filters={filters}
                            showGrain={report.supports_grain}
                            showIncludeVoided={
                                permissions.enhanced_exports &&
                                report.key === 'sales_summary'
                            }
                            submitLabel={t('pages.reports.apply_filters')}
                        />

                        {summaryCards.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {summaryCards.map(([key, value], index) => (
                                    <MetricCard
                                        key={key}
                                        label={humanizeKey(key)}
                                        value={value ?? '—'}
                                        accent={
                                            index % 3 === 1
                                                ? 'accent'
                                                : index % 3 === 2
                                                  ? 'muted'
                                                  : 'primary'
                                        }
                                    />
                                ))}
                            </div>
                        ) : null}

                        {derivedChart.length > 0 || report.supports_grain ? (
                            <ReportChartPanel
                                kind={
                                    derivedChart.length > 8 &&
                                    chartKind === 'pie'
                                        ? 'horizontal-bar'
                                        : chartKind
                                }
                                data={derivedChart}
                                emptyLabel={t('pages.reports.chart_empty')}
                                title={t('pages.reports.chart')}
                                currency={currency}
                                reportKey={report.key}
                            />
                        ) : null}

                        <section className="overflow-hidden rounded-2xl border border-border/80 bg-card/80 shadow-sm">
                            <div className="border-b border-border/70 px-4 py-3">
                                <h2 className="font-display text-lg font-semibold">
                                    {t('pages.reports.results')}
                                </h2>
                            </div>
                            {rows.length === 0 ? (
                                <EmptyState
                                    title={t('pages.reports.no_results_title')}
                                    description={t(
                                        'pages.reports.no_results_description',
                                    )}
                                    className="min-h-[12rem]"
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                {displayColumns.map(
                                                    (column) => (
                                                        <th
                                                            key={column}
                                                            className="px-4 py-3 font-medium"
                                                        >
                                                            {humanizeKey(
                                                                column,
                                                            )}
                                                        </th>
                                                    ),
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row, index) => (
                                                <tr
                                                    key={index}
                                                    className="border-t border-border/60"
                                                >
                                                    {displayColumns.map(
                                                        (column) => (
                                                            <td
                                                                key={column}
                                                                className="px-4 py-3 tabular-nums"
                                                            >
                                                                {formatCell(
                                                                    row[column],
                                                                )}
                                                            </td>
                                                        ),
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

function deriveChartFromRows(
    reportKey: string,
    rows: Array<Record<string, string | number | null | boolean>>,
): Array<{ label: string; value: number }> {
    if (rows.length === 0) {
        return [];
    }

    if (reportKey === 'product_performance') {
        return rows.slice(0, 8).map((row) => ({
            label: String(row.product_name ?? row.name ?? 'Product'),
            value: Number(row.revenue_minor ?? row.units_sold ?? 0),
        }));
    }

    if (reportKey === 'gross_profit') {
        return rows.slice(0, 8).map((row) => ({
            label: String(row.product_name ?? row.name ?? 'Product'),
            value: Number(row.gross_profit_minor ?? row.profit_minor ?? 0),
        }));
    }

    return [];
}

function formatCell(
    value: string | number | null | boolean | undefined,
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return String(value);
}

ReportShow.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: reportsIndex(),
        },
    ],
};
