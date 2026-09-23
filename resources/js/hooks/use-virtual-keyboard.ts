import { useEffect, useSyncExternalStore } from 'react';

const KEYBOARD_OPEN_PX = 80;

function visualViewport(): VisualViewport | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.visualViewport ?? null;
}

function readKeyboardInset(): number {
    const viewport = visualViewport();

    if (!viewport) {
        return 0;
    }

    return Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
}

function applyKeyboardCssVars() {
    if (typeof document === 'undefined') {
        return;
    }

    const viewport = visualViewport();
    const root = document.documentElement;
    const inset = readKeyboardInset();

    root.style.setProperty('--keyboard-inset', `${inset}px`);
    root.style.setProperty(
        '--vv-height',
        `${viewport?.height ?? window.innerHeight}px`,
    );
    root.style.setProperty(
        '--vv-offset-top',
        `${viewport?.offsetTop ?? 0}px`,
    );
}

function subscribeVirtualKeyboard(onStoreChange: () => void) {
    const viewport = visualViewport();
    const notify = () => {
        applyKeyboardCssVars();
        onStoreChange();
    };

    viewport?.addEventListener('resize', notify);
    viewport?.addEventListener('scroll', notify);
    window.addEventListener('resize', notify);
    notify();

    return () => {
        viewport?.removeEventListener('resize', notify);
        viewport?.removeEventListener('scroll', notify);
        window.removeEventListener('resize', notify);
    };
}

function isEditableTarget(target: EventTarget | null): target is HTMLElement {
    return (
        target instanceof HTMLElement &&
        target.matches('input, textarea, select, [contenteditable="true"]')
    );
}

export function useVirtualKeyboard() {
    const inset = useSyncExternalStore(
        subscribeVirtualKeyboard,
        readKeyboardInset,
        () => 0,
    );

    useEffect(() => {
        const onFocusIn = (event: FocusEvent) => {
            if (!isEditableTarget(event.target)) {
                return;
            }

            const field = event.target;

            window.setTimeout(() => {
                field.scrollIntoView({
                    block: 'center',
                    inline: 'nearest',
                });
            }, 300);
        };

        document.addEventListener('focusin', onFocusIn);

        return () => document.removeEventListener('focusin', onFocusIn);
    }, []);

    return {
        inset,
        isOpen: inset > KEYBOARD_OPEN_PX,
    };
}
