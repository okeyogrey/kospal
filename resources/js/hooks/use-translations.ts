import { usePage } from '@inertiajs/react';

type NestedValue = string | { [key: string]: NestedValue };

function resolvePath(source: NestedValue, path: string): string | undefined {
    const segments = path.split('.');
    let current: NestedValue | undefined = source;

    for (const segment of segments) {
        if (typeof current !== 'object' || current === null) {
            return undefined;
        }

        current = current[segment];
    }

    return typeof current === 'string' ? current : undefined;
}

export function useTranslations() {
    const { translations, locale } = usePage().props;

    const t = (key: string, fallback?: string): string => {
        return (
            resolvePath(translations as NestedValue, key) ??
            fallback ??
            key
        );
    };

    return { t, locale, translations };
}
