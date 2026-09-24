import { useTranslations } from '@/hooks/use-translations';

export default function AppLogo() {
    const { t } = useTranslations();

    return (
        <>
            <img
                src="/brand/mark.png"
                alt=""
                draggable={false}
                className="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block"
            />
            <span className="flex min-w-0 flex-1 flex-col items-start gap-0.5 group-data-[collapsible=icon]:hidden">
                <img
                    src="/brand/logo.png"
                    alt={t('brand.name', 'KOSPAL')}
                    draggable={false}
                    className="h-auto w-36 max-w-full object-contain"
                />
                <span className="truncate text-xs text-sidebar-foreground/70">
                    {t('brand.tagline', 'Retail management')}
                </span>
            </span>
        </>
    );
}
