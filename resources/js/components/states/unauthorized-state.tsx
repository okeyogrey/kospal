import { Link } from '@inertiajs/react';
import { ShieldOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type UnauthorizedStateProps = {
    title?: string;
    description?: string;
    className?: string;
};

export function UnauthorizedState({
    title,
    description,
    className,
}: UnauthorizedStateProps) {
    const { t } = useTranslations();

    return (
        <div
            className={cn(
                'flex min-h-[16rem] flex-col items-center justify-center gap-4 px-6 py-12 text-center',
                className,
            )}
            role="alert"
            aria-live="polite"
        >
            <div
                className="flex size-12 items-center justify-center rounded-2xl bg-accent text-accent-foreground"
                aria-hidden
            >
                <ShieldOff className="size-5" aria-hidden />
            </div>
            <div className="space-y-1">
                <h2 className="font-display text-lg font-semibold text-foreground">
                    {title ?? t('states.unauthorized.title')}
                </h2>
                <p className="max-w-md text-sm text-muted-foreground">
                    {description ?? t('states.unauthorized.description')}
                </p>
            </div>
            <Button asChild variant="default">
                <Link href={dashboard()}>{t('nav.dashboard')}</Link>
            </Button>
        </div>
    );
}
