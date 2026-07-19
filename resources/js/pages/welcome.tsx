import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('brand.name')} />
            <div className="relative min-h-svh overflow-hidden bg-[oklch(0.22_0.045_165)] text-[oklch(0.97_0.01_165)]">
                <div
                    className="pointer-events-none absolute inset-0 opacity-80"
                    style={{
                        background:
                            'radial-gradient(ellipse 80% 60% at 70% 20%, oklch(0.45 0.08 165 / 0.55), transparent 60%), radial-gradient(ellipse 50% 40% at 10% 80%, oklch(0.55 0.1 90 / 0.25), transparent 55%)',
                    }}
                />
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.12]"
                    style={{
                        backgroundImage:
                            'linear-gradient(oklch(0.95 0.02 90 / 0.15) 1px, transparent 1px), linear-gradient(90deg, oklch(0.95 0.02 90 / 0.15) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-3">
                        <AppLogoIcon className="size-10 text-[oklch(0.4_0.1_165)]" />
                        <span className="font-display text-xl font-semibold tracking-wide">
                            {t('brand.name')}
                        </span>
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild variant="secondary" size="sm">
                                <Link href={dashboard()}>
                                    {t('welcome.cta_primary')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>
                                        {t('welcome.cta_login')}
                                    </Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={register()}>
                                        {t('welcome.cta_register')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex min-h-[calc(100svh-5.5rem)] w-full max-w-6xl flex-col justify-end px-6 pb-16 pt-8 md:justify-center md:pb-24">
                    <div className="max-w-2xl space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                        <p className="font-display text-5xl font-semibold tracking-tight text-[oklch(0.9_0.08_90)] sm:text-6xl md:text-7xl">
                            {t('brand.name')}
                        </p>
                        <h1 className="max-w-xl text-xl font-medium leading-snug text-[oklch(0.96_0.01_165)] sm:text-2xl">
                            {t('welcome.title')}
                        </h1>
                        <p className="max-w-lg text-sm leading-relaxed text-[oklch(0.88_0.02_165)] sm:text-base">
                            {t('welcome.subtitle')}
                        </p>
                        <div className="flex flex-wrap gap-3 pt-2">
                            {auth.user ? (
                                <Button asChild size="lg">
                                    <Link href={dashboard()}>
                                        {t('welcome.cta_primary')}
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild size="lg">
                                        <Link href={register()}>
                                            {t('welcome.cta_register')}
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        size="lg"
                                        variant="secondary"
                                    >
                                        <Link href={login()}>
                                            {t('welcome.cta_login')}
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
