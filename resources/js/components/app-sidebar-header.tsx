import { Link, usePage } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { BranchSelector } from '@/components/branch-selector';
import { LanguageSelector } from '@/components/language-selector';
import { NotificationBell } from '@/components/notification-bell';
import { CashDrawerWidget } from '@/components/cash-drawer-widget';
import { ShiftClockWidget } from '@/components/shift-clock-widget';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useTranslations } from '@/hooks/use-translations';
import { isNavKeyAllowed } from '@/lib/navigation';
import { pos as salesPos } from '@/routes/sales';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { t } = useTranslations();
    const { auth, navigation } = usePage().props;
    const canOpenPos = isNavKeyAllowed(
        'pos',
        navigation.allowedKeys,
        auth.role,
    );

    return (
        <header className="sticky top-0 z-20 flex h-[calc(3.5rem+env(safe-area-inset-top))] shrink-0 items-center justify-between gap-1 overflow-x-hidden border-b border-border/70 bg-background/85 px-2 pt-[env(safe-area-inset-top)] backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(3rem+env(safe-area-inset-top))] sm:h-[calc(4rem+env(safe-area-inset-top))] sm:gap-2 sm:px-4 md:px-6">
            <div className="flex min-w-0 items-center gap-1 sm:gap-2">
                <SidebarTrigger className="-ml-0.5 shrink-0" />
                <div className="hidden min-w-0 overflow-hidden sm:block">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="flex min-w-0 shrink-0 items-center gap-0.5 sm:gap-2">
                {canOpenPos ? (
                    <>
                        <Button
                            asChild
                            size="sm"
                            className="hidden sm:inline-flex"
                        >
                            <Link href={salesPos()} prefetch>
                                <MonitorSmartphone className="size-4" />
                                {t('topbar.open_pos', 'Open POS')}
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="icon"
                            className="size-9 sm:hidden"
                            aria-label={t('topbar.open_pos', 'Open POS')}
                        >
                            <Link href={salesPos()} prefetch>
                                <MonitorSmartphone className="size-4" />
                            </Link>
                        </Button>
                    </>
                ) : null}
                <ShiftClockWidget />
                <CashDrawerWidget />
                <BranchSelector />
                <LanguageSelector />
                <NotificationBell />
            </div>
        </header>
    );
}
