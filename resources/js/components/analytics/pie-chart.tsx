import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';
import { cn } from '@/lib/utils';
import {
    type ChartPoint,
    chartColor,
    shortLabel,
} from '@/components/analytics/chart-theme';

export function AnalyticsPieChart({
    data,
    emptyLabel = 'No data for this period',
    className,
    valueFormatter,
}: {
    data: ChartPoint[];
    emptyLabel?: string;
    className?: string;
    valueFormatter?: (value: number) => string;
}) {
    if (data.length === 0) {
        return (
            <div
                className={cn(
                    'flex min-h-[14rem] items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 text-sm text-muted-foreground',
                    className,
                )}
            >
                {emptyLabel}
            </div>
        );
    }

    const formatValue = valueFormatter ?? ((value: number) => value.toLocaleString());
    const total = data.reduce((sum, point) => sum + point.value, 0);

    return (
        <div className={cn('h-[16rem] w-full sm:h-[18rem]', className)}>
            <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie
                        data={data}
                        dataKey="value"
                        nameKey="label"
                        cx="50%"
                        cy="46%"
                        innerRadius="52%"
                        outerRadius="78%"
                        paddingAngle={2}
                        stroke="var(--card)"
                        strokeWidth={2}
                    >
                        {data.map((point, index) => (
                            <Cell
                                key={point.label}
                                fill={chartColor(index)}
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        contentStyle={tooltipStyle}
                        formatter={(value, name) => {
                            const amount = Number(value ?? 0);
                            const share =
                                total > 0
                                    ? Math.round((amount / total) * 100)
                                    : 0;

                            return [
                                `${formatValue(amount)} (${share}%)`,
                                String(name ?? ''),
                            ];
                        }}
                    />
                    <Legend
                        verticalAlign="bottom"
                        iconType="circle"
                        formatter={(value) => (
                            <span className="text-xs text-muted-foreground">
                                {shortLabel(String(value), 18)}
                            </span>
                        )}
                    />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}

const tooltipStyle = {
    background: 'var(--card)',
    border: '1px solid var(--border)',
    borderRadius: '0.75rem',
    boxShadow: '0 8px 24px color-mix(in oklch, var(--foreground) 8%, transparent)',
};
