import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import InstallAppHint from '@/components/install-app-hint';
import { useTranslations } from '@/hooks/use-translations';
import { useVirtualKeyboard } from '@/hooks/use-virtual-keyboard';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslations();
    const keyboard = useVirtualKeyboard();

    return (
        <div
            className={cn(
                'kospal-shell-bg flex w-full max-w-full flex-col items-center justify-start overflow-x-hidden overflow-y-auto px-4 py-8 pt-[max(2rem,env(safe-area-inset-top))] sm:p-6 sm:pt-[max(1.5rem,env(safe-area-inset-top))] md:p-10 md:pt-[max(2.5rem,env(safe-area-inset-top))]',
                keyboard.isOpen ? 'justify-start' : 'sm:justify-center',
            )}
            style={{
                minHeight: 'var(--vv-height, 100dvh)',
                maxHeight: 'var(--vv-height, 100dvh)',
            }}
        >
            <div className="w-full max-w-sm min-w-0 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div className="flex flex-col gap-6 sm:gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex size-12 items-center justify-center overflow-hidden rounded-2xl shadow-sm">
                                <AppLogoIcon className="size-12" />
                            </div>
                            <span className="font-display text-lg font-semibold tracking-wide">
                                {t('brand.name')}
                            </span>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium text-balance">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                    <InstallAppHint />
                </div>
            </div>
        </div>
    );
}
