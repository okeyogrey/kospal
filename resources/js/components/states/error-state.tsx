import { AlertTriangle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type ErrorStateProps = {
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

export function ErrorState({
    title,
    description,
    action,
    className,
}: ErrorStateProps) {
    const { t } = useTranslations();

    return (
        <div
            className={cn(
                'flex min-h-[16rem] flex-col items-center justify-center gap-4 px-6 py-12 text-center',
                className,
            )}
            role="alert"
            aria-live="assertive"
        >
            <div
                className="flex size-12 items-center justify-center rounded-2xl bg-destructive/10 text-destructive"
                aria-hidden
            >
                <AlertTriangle className="size-5" aria-hidden />
            </div>
            <div className="space-y-1">
                <h2 className="font-display text-lg font-semibold text-foreground">
                    {title ?? t('states.error.title')}
                </h2>
                <p className="max-w-md text-sm text-muted-foreground">
                    {description ?? t('states.error.description')}
                </p>
            </div>
            {action}
        </div>
    );
}
