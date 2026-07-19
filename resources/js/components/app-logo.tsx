import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslations } from '@/hooks/use-translations';

export default function AppLogo() {
    const { t } = useTranslations();

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-sidebar text-sidebar-foreground shadow-sm ring-1 ring-sidebar-border">
                <AppLogoIcon className="size-9" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-display text-base font-semibold tracking-wide text-sidebar-foreground">
                    {t('brand.name', 'KOSPAL')}
                </span>
                <span className="truncate text-xs text-sidebar-foreground/70">
                    {t('brand.tagline', 'Retail management')}
                </span>
            </div>
        </>
    );
}
