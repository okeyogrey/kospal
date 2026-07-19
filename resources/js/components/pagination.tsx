import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export function Pagination({
    links,
}: {
    links: PaginationLink[];
}) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-1 border-t border-border/70 p-3">
            {links.map((link, index) => {
                if (!link.url) {
                    return (
                        <Button
                            key={`${link.label}-${index}`}
                            size="sm"
                            variant="ghost"
                            disabled
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                }

                return (
                    <Button
                        key={`${link.label}-${index}`}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        asChild
                    >
                        <Link
                            href={link.url}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    </Button>
                );
            })}
        </nav>
    );
}
