import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';

export function NotificationBell() {
    const { t } = useTranslations();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-9"
                    aria-label={t('topbar.notifications')}
                >
                    <Bell className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuLabel>
                    {t('topbar.notifications')}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div className="px-2 py-6 text-center text-sm text-muted-foreground">
                    {t('topbar.no_notifications')}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
