import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/states';

type PagePlaceholderProps = {
    title: string;
    description?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    icon?: ReactNode;
};

export function PagePlaceholder({
    title,
    description,
    emptyTitle,
    emptyDescription,
    icon,
}: PagePlaceholderProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
                        {title}
                    </h1>
                    {description ? (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                <div className="rounded-2xl border border-border/80 bg-card/80 shadow-sm">
                    <EmptyState
                        title={emptyTitle}
                        description={emptyDescription}
                        icon={icon}
                    />
                </div>
            </div>
        </>
    );
}
