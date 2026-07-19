import { router, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { branch as switchBranch } from '@/routes/workspace';

export function BranchSelector() {
    const { workspace } = usePage().props;
    const { t } = useTranslations();
    const hasBranches = workspace.branches.length > 0;

    const selectBranch = (branchId: number) => {
        if (workspace.branch?.id === branchId) {
            return;
        }

        router.post(
            switchBranch.url(),
            { branch_id: branchId },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 max-w-[10rem] gap-2 border-border/80 bg-background/70 px-2.5 sm:max-w-[14rem]"
                    aria-label={t('topbar.branch')}
                >
                    <MapPin className="size-4 shrink-0 text-primary" />
                    <span className="truncate text-xs font-medium">
                        {workspace.branch?.name ?? t('topbar.no_branch')}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{t('topbar.branch')}</DropdownMenuLabel>
                {hasBranches ? (
                    workspace.branches.map((branch) => (
                        <DropdownMenuItem
                            key={branch.id}
                            onClick={() => selectBranch(branch.id)}
                            className={
                                branch.id === workspace.branch?.id
                                    ? 'bg-accent'
                                    : undefined
                            }
                        >
                            {branch.name}
                        </DropdownMenuItem>
                    ))
                ) : (
                    <DropdownMenuItem disabled>
                        {t('topbar.no_branch')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
