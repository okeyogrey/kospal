import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
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
                className="kospal-shell-bg overflow-x-hidden"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SubscriptionRestrictionBanner />
                <main id="main-content" tabIndex={-1} className="outline-none">
                    {children}
                </main>
            </AppContent>
        </AppShell>
    );
}
