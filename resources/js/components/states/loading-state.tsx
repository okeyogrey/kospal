import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type LoadingStateProps = {
    title?: string;
    description?: string;
    className?: string;
};

export function LoadingState({
    title,
    description,
    className,
}: LoadingStateProps) {
    const { t } = useTranslations();

    return (
        <div
            className={cn(
                'flex min-h-[16rem] flex-col items-center justify-center gap-3 px-6 py-12 text-center',
                className,
            )}
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <Spinner className="size-6 text-primary" aria-hidden />
            <div className="space-y-1">
                <p className="font-medium text-foreground">
                    {title ?? t('states.loading.title')}
                </p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description ?? t('states.loading.description')}
                </p>
            </div>
        </div>
    );
}
