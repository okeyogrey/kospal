const STANDALONE_CLASS = 'kospal-standalone';
const BOOTED_CLASS = 'kospal-booted';

type StandaloneNavigator = Navigator & { standalone?: boolean };

export function isTauriRuntime(): boolean {
    return typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window;
}

export function isStandaloneDisplay(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const navigatorStandalone =
        (window.navigator as StandaloneNavigator).standalone === true;

    return (
        navigatorStandalone ||
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        window.matchMedia('(display-mode: minimal-ui)').matches
    );
}

export function isIosDevice(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

export function isAndroidDevice(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return /android/i.test(window.navigator.userAgent);
}

export function markStandaloneMode(): void {
    if (typeof document === 'undefined' || !isStandaloneDisplay()) {
        return;
    }

    document.documentElement.classList.add(STANDALONE_CLASS);
}

export function markAppReady(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.add(BOOTED_CLASS);
}

export function registerServiceWorker(): void {
    if (typeof window === 'undefined' || isTauriRuntime()) {
        return;
    }

    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    const register = () => {
        void navigator.serviceWorker.register('/sw.js', { scope: '/' });
    };

    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register);
    }
}

markStandaloneMode();
registerServiceWorker();
