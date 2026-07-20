import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editBackup } from '@/routes/backup';
import { edit as editDatabase } from '@/routes/database';
import { edit as editDataStorage } from '@/routes/data-storage';
import { edit as editErrorReporting } from '@/routes/error-reporting';
import { edit as editHealth } from '@/routes/health';
import { edit as editLicense } from '@/routes/license';
import { edit as editLocal } from '@/routes/local';
import { edit as editPrinter } from '@/routes/printer';
import { edit } from '@/routes/profile';
import { edit as editRestore } from '@/routes/restore';
import { edit as editSecurity } from '@/routes/security';
import { edit as editUpdates } from '@/routes/updates';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth, deployment } = usePage().props as {
        auth?: { role?: string | null };
        deployment?: { is_desktop?: boolean };
    };

    const showDesktop =
        Boolean(deployment?.is_desktop) && auth?.role === 'owner';

    const sidebarNavItems: NavItem[] = [
        {
            title: 'Profile',
            href: edit(),
            icon: null,
        },
        {
            title: 'Security',
            href: editSecurity(),
            icon: null,
        },
        {
            title: 'Appearance',
            href: editAppearance(),
            icon: null,
        },
        ...(showDesktop
            ? [
                  {
                      title: 'License',
                      href: editLicense(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Local',
                      href: editLocal(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Backup',
                      href: editBackup(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Backup wizard',
                      href: '/settings/backup/wizard',
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Restore',
                      href: editRestore(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Health',
                      href: editHealth(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Error reporting',
                      href: editErrorReporting(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Updates',
                      href: editUpdates(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Printer',
                      href: editPrinter(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Printer wizard',
                      href: '/settings/printer/wizard',
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Database',
                      href: editDatabase(),
                      icon: null,
                  } satisfies NavItem,
                  {
                      title: 'Storage',
                      href: editDataStorage(),
                      icon: null,
                  } satisfies NavItem,
              ]
            : []),
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
