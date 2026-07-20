import { Breadcrumbs } from '@/components/breadcrumbs';
import { BranchSelector } from '@/components/branch-selector';
import { LanguageSelector } from '@/components/language-selector';
import { NotificationBell } from '@/components/notification-bell';
import { CashDrawerWidget } from '@/components/cash-drawer-widget';
import { ShiftClockWidget } from '@/components/shift-clock-widget';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border/70 bg-background/85 px-4 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <div className="min-w-0 overflow-hidden">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                <ShiftClockWidget />
                <CashDrawerWidget />
                <BranchSelector />
                <LanguageSelector />
                <NotificationBell />
            </div>
        </header>
    );
}
