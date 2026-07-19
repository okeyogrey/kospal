import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function MetricCard({
    label,
    value,
    hint,
    icon,
    accent = 'primary',
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
    icon?: ReactNode;
    accent?: 'primary' | 'accent' | 'warning' | 'muted';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-2xl border border-border/80 bg-card/80 p-4 shadow-sm',
                className,
            )}
        >
            <div
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-1 opacity-90',
                    accent === 'primary' && 'bg-primary/70',
                    accent === 'accent' && 'bg-accent-foreground/40',
                    accent === 'warning' && 'bg-amber-500/80',
                    accent === 'muted' && 'bg-muted-foreground/30',
                )}
            />
            <div className="flex items-start justify-between gap-3">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                {icon ? (
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                        {icon}
                    </div>
                ) : null}
            </div>
            <p className="mt-2 font-display text-2xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {hint ? (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}
