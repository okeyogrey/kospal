import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    title?: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    title,
    description,
    icon,
    action,
    className,
}: EmptyStateProps) {
    const { t } = useTranslations();

    return (
        <div
            className={cn(
                'flex min-h-[16rem] flex-col items-center justify-center gap-4 px-6 py-12 text-center',
                className,
            )}
            role="status"
            aria-live="polite"
        >
            <div
                className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary"
                aria-hidden
            >
                {icon ?? <Inbox className="size-5" aria-hidden />}
            </div>
            <div className="space-y-1">
                <h2 className="font-display text-lg font-semibold text-foreground">
                    {title ?? t('states.empty.title')}
                </h2>
                <p className="max-w-md text-sm text-muted-foreground">
                    {description ?? t('states.empty.description')}
                </p>
            </div>
            {action}
        </div>
    );
}
