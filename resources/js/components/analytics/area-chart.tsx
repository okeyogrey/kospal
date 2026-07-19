import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { cn } from '@/lib/utils';
import {
    type ChartPoint,
    shortLabel,
} from '@/components/analytics/chart-theme';

export function AnalyticsAreaChart({
    data,
    emptyLabel = 'No data for this period',
    className,
    valueFormatter,
    tooltipFormatter,
}: {
    data: ChartPoint[];
    emptyLabel?: string;
    className?: string;
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

    return (
        <div className={cn('h-[14rem] w-full sm:h-[16rem]', className)}>
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart
                    data={data}
                    margin={{ top: 8, right: 8, left: 0, bottom: 4 }}
                >
                    <defs>
                        <linearGradient
                            id="analyticsAreaFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.35}
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.02}
                            />
                        </linearGradient>
                    </defs>
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
                        contentStyle={tooltipStyle}
                        formatter={(value) => [
                            formatTooltip(Number(value ?? 0)),
                            '',
                        ]}
                        labelStyle={{ color: 'var(--foreground)' }}
                    />
                    <Area
                        type="monotone"
                        dataKey="value"
                        stroke="var(--chart-1)"
                        strokeWidth={2.5}
                        fill="url(#analyticsAreaFill)"
                        activeDot={{ r: 5, fill: 'var(--chart-1)' }}
                    />
                </AreaChart>
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
