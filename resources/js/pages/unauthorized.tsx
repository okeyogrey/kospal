import { Head } from '@inertiajs/react';
import { UnauthorizedState } from '@/components/states';
import { useTranslations } from '@/hooks/use-translations';
import { unauthorized } from '@/routes';

export default function Unauthorized() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('pages.unauthorized.title')} />
            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <div className="rounded-2xl border border-border/80 bg-card/80 shadow-sm">
                    <UnauthorizedState
                        title={t('pages.unauthorized.title')}
                        description={t('pages.unauthorized.description')}
                    />
                </div>
            </div>
        </>
    );
}

Unauthorized.layout = {
    breadcrumbs: [
        {
            title: 'Unauthorized',
            href: unauthorized(),
        },
    ],
};
