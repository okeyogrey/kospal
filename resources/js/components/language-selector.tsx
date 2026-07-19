import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { update as updateLocale } from '@/routes/locale';
import type { LocaleCode } from '@/types';

export function LanguageSelector() {
    const { locale, locales } = usePage().props;
    const { t } = useTranslations();

    const switchLocale = (next: LocaleCode) => {
        if (next === locale) {
            return;
        }

        router.post(
            updateLocale.url(),
            { locale: next },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-9 gap-2 px-2.5"
                    aria-label={t('topbar.language')}
                >
                    <Languages className="size-4" />
                    <span className="hidden text-xs font-medium uppercase sm:inline">
                        {locale}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>{t('topbar.language')}</DropdownMenuLabel>
                {(Object.entries(locales) as [LocaleCode, string][]).map(
                    ([code, label]) => (
                        <DropdownMenuItem
                            key={code}
                            onClick={() => switchLocale(code)}
                            className={code === locale ? 'bg-accent' : undefined}
                            aria-current={code === locale ? 'true' : undefined}
                        >
                            {label}
                        </DropdownMenuItem>
                    ),
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
