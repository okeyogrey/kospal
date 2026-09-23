import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ShopSyncBanner } from '@/components/shop-sync-banner';
import { SubscriptionRestrictionBanner } from '@/components/subscription-restriction-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-primary focus:px-3 focus:py-2 focus:text-primary-foreground focus:shadow-md"
            >
                Skip to main content
            </a>
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="kospal-shell-bg h-dvh max-h-dvh overflow-hidden"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SubscriptionRestrictionBanner />
                <ShopSyncBanner />
                <main
                    id="main-content"
                    tabIndex={-1}
                    className="flex min-h-0 min-w-0 flex-1 flex-col overflow-auto outline-none"
                >
                    {children}
                </main>
            </AppContent>
        </AppShell>
    );
}
