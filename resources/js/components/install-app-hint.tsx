import { Share, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import {
    isAndroidDevice,
    isIosDevice,
    isStandaloneDisplay,
    isTauriRuntime,
} from '@/pwa';

const DISMISS_KEY = 'kospal-a2hs-dismissed';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

function canOfferManualInstall(): boolean {
    if (typeof window === 'undefined' || isTauriRuntime()) {
        return false;
    }

    if (isStandaloneDisplay()) {
        return false;
    }

    if (window.localStorage.getItem(DISMISS_KEY) === '1') {
        return false;
    }

    if (!window.matchMedia('(max-width: 767px)').matches) {
        return false;
    }

    return isIosDevice() || isAndroidDevice();
}

export default function InstallAppHint() {
    const { t } = useTranslations();
    const [visible, setVisible] = useState(canOfferManualInstall);
    const [installEvent, setInstallEvent] =
        useState<BeforeInstallPromptEvent | null>(null);

    useEffect(() => {
        if (typeof window === 'undefined' || isTauriRuntime()) {
            return;
        }

        if (isStandaloneDisplay()) {
            return;
        }

        if (window.localStorage.getItem(DISMISS_KEY) === '1') {
            return;
        }

        if (!window.matchMedia('(max-width: 767px)').matches) {
            return;
        }

        const onBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();
            setInstallEvent(event as BeforeInstallPromptEvent);
            setVisible(true);
        };

        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);

        return () => {
            window.removeEventListener(
                'beforeinstallprompt',
                onBeforeInstallPrompt,
            );
        };
    }, []);

    if (!visible) {
        return null;
    }

    const dismiss = () => {
        window.localStorage.setItem(DISMISS_KEY, '1');
        setVisible(false);
    };

    const install = async () => {
        if (!installEvent) {
            return;
        }

        await installEvent.prompt();
        setVisible(false);
    };

    const manualHint = isIosDevice()
        ? t(
              'pwa.install_ios',
              'Tap Share, then Add to Home Screen. Open it from your home screen to use it like an app.',
          )
        : t(
              'pwa.install_android',
              'In Chrome, tap ⋮ then Install app — not Add to Home screen. If you only see Add to Home screen, Chrome will keep showing the address bar.',
          );

    return (
        <div className="rounded-xl border border-border/80 bg-card/95 p-3 text-left shadow-sm">
            <div className="flex items-start gap-2">
                <Share className="mt-0.5 size-4 shrink-0 text-primary" />
                <div className="min-w-0 flex-1 space-y-1">
                    <p className="text-sm font-medium">
                        {t('pwa.install_title', 'Install KOSPAL')}
                    </p>
                    {installEvent ? null : (
                        <p className="text-xs text-balance text-muted-foreground">
                            {manualHint}
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                    onClick={dismiss}
                    aria-label={t('pwa.dismiss', 'Not now')}
                >
                    <X className="size-4" />
                </button>
            </div>
            {installEvent ? (
                <Button
                    type="button"
                    className="mt-3 h-10 w-full"
                    onClick={() => void install()}
                >
                    {t('pwa.install_button', 'Install app')}
                </Button>
            ) : null}
        </div>
    );
}
