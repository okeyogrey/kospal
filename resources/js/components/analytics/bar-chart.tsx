import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { cn } from '@/lib/utils';
import {
    type ChartPoint,
    chartColor,
    shortLabel,
} from '@/components/analytics/chart-theme';

export function AnalyticsBarChart({
    data,
    emptyLabel = 'No data for this period',
    className,
    horizontal = false,
    valueFormatter,
    tooltipFormatter,
}: {
    data: ChartPoint[];
    emptyLabel?: string;
    className?: string;
    horizontal?: boolean;
    valueFormatter?: (value: number) => string;
    tooltipFormatter?: (value: number) => string;
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

    const formatTick =
        valueFormatter ?? ((value: number) => value.toLocaleString());
    const formatTooltip =
        tooltipFormatter ?? valueFormatter ?? ((value: number) => value.toLocaleString());

    if (horizontal) {
        return (
            <div className={cn('h-[16rem] w-full sm:h-[18rem]', className)}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={data}
                        layout="vertical"
                        margin={{ top: 4, right: 12, left: 4, bottom: 4 }}
                    >
                        <CartesianGrid
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                            horizontal={false}
                        />
                        <XAxis
                            type="number"
                            tickLine={false}
                            axisLine={false}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            tickFormatter={(value: number) => formatTick(value)}
                        />
                        <YAxis
                            type="category"
                            dataKey="label"
                            width={88}
                            tickLine={false}
                            axisLine={false}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            tickFormatter={(label: string) => shortLabel(label, 14)}
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--muted)', opacity: 0.45 }}
                            contentStyle={tooltipStyle}
                            formatter={(value) => [
                                formatTooltip(Number(value ?? 0)),
                                '',
                            ]}
                            labelStyle={{ color: 'var(--foreground)' }}
                        />
                        <Bar dataKey="value" radius={[0, 6, 6, 0]} maxBarSize={28}>
                            {data.map((point, index) => (
                                <Cell
                                    key={point.label}
                                    fill={chartColor(index)}
                                />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
        );
    }

    return (
        <div className={cn('h-[14rem] w-full sm:h-[16rem]', className)}>
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={data}
                    margin={{ top: 8, right: 8, left: 0, bottom: 4 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                        vertical={false}
                    />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                        tickFormatter={(label: string) => shortLabel(label, 10)}
                        interval="preserveStartEnd"
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        width={56}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                        tickFormatter={(value: number) => formatTick(value)}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--muted)', opacity: 0.45 }}
                        contentStyle={tooltipStyle}
                        formatter={(value) => [
                            formatTooltip(Number(value ?? 0)),
                            '',
                        ]}
                        labelStyle={{ color: 'var(--foreground)' }}
                    />
                    <Bar dataKey="value" radius={[6, 6, 0, 0]} maxBarSize={40}>
                        {data.map((point, index) => (
                            <Cell key={point.label} fill={chartColor(index)} />
                        ))}
                    </Bar>
                </BarChart>
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
