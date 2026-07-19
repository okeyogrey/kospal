import { Head, Link } from '@inertiajs/react';
import {
    ChartColumn,
    ChartPie,
    Lock,
    TrendingUp,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import { index as reportsIndex, show as reportShow } from '@/routes/reports';
import { index as subscriptionIndex } from '@/routes/subscription';

type ReportCard = {
    key: string;
    label: string;
    description: string;
    group: string;
    min_plan: string;
    available: boolean;
    supports_csv: boolean;
};

const GROUP_LABELS: Record<string, string> = {
    sales: 'Sales',
    expenses: 'Expenses',
    inventory: 'Inventory',
    compliance: 'Compliance',
};

const PIE_KEYS = new Set([
    'expenses_summary',
    'sales_by_payment_method',
    'stock_movement_summary',
]);

const TREND_KEYS = new Set([
    'sales_summary',
    'sales_trends',
    'expense_trend',
]);

function ReportIcon({ reportKey }: { reportKey: string }) {
    if (PIE_KEYS.has(reportKey)) {
        return <ChartPie className="size-4" />;
    }

    if (TREND_KEYS.has(reportKey)) {
        return <TrendingUp className="size-4" />;
    }

    return <ChartColumn className="size-4" />;
}

export default function ReportsIndex({
    reports,
    plan,
    features,
}: {
    reports: Record<string, ReportCard[]>;
    plan: string;
    features: {
        advanced_reports: boolean;
        csv_export: boolean;
        consolidated_reports: boolean;
        audit_logs: boolean;
        enhanced_exports: boolean;
    };
    currency: string;
}) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('pages.reports.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.reports.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('pages.reports.description')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{plan}</Badge>
                        {features.csv_export ? (
                            <Badge variant="outline">CSV export</Badge>
                        ) : null}
                        {features.enhanced_exports ? (
                            <Badge variant="outline">Enhanced exports</Badge>
                        ) : null}
                    </div>
                </div>

                <div className="space-y-8">
                    {Object.entries(reports).map(([group, items]) => (
                        <section key={group} className="space-y-3">
                            <h2 className="font-display text-lg font-semibold">
                                {GROUP_LABELS[group] ?? group}
                            </h2>
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {items.map((report) => (
                                    <Link
                                        key={report.key}
                                        href={reportShow(report.key)}
                                        className="group relative overflow-hidden rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm transition hover:border-primary/40"
                                    >
                                        <div className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-primary/50 opacity-0 transition group-hover:opacity-100" />
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                                                {report.available ? (
                                                    <ReportIcon
                                                        reportKey={report.key}
                                                    />
                                                ) : (
                                                    <Lock className="size-4" />
                                                )}
                                            </div>
                                            {!report.available ? (
                                                <Badge variant="secondary">
                                                    {report.min_plan}+
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <h3 className="mt-3 font-medium group-hover:text-primary">
                                            {report.label}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {report.description}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>

                {!features.advanced_reports ? (
                    <div className="rounded-2xl border border-dashed border-border/80 bg-muted/20 p-5">
                        <p className="font-medium">
                            {t('pages.reports.upgrade_teaser_title')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.reports.upgrade_teaser_description')}
                        </p>
                        <Link
                            href={subscriptionIndex()}
                            className="mt-3 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
                        >
                            {t('pages.reports.upgrade_cta')}
                        </Link>
                    </div>
                ) : null}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: reportsIndex(),
        },
    ],
};
