export type ChartPoint = {
    label: string;
    value: number;
};

export const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
] as const;

export function chartColor(index: number): string {
    return CHART_COLORS[index % CHART_COLORS.length];
}

export function shortLabel(label: string, max = 12): string {
    if (label.length <= max) {
        return label;
    }

    return `${label.slice(0, max - 1)}…`;
}
