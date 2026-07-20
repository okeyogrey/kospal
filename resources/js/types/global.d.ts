import type { Auth } from '@/types/auth';
import type {
    LocaleCode,
    NavigationShare,
    Translations,
    Workspace,
} from '@/types/kospal';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            deployment: {
                mode: 'desktop' | 'web';
                is_desktop: boolean;
            };
            auth: Auth;
            locale: LocaleCode;
            locales: Record<LocaleCode, string>;
            currencies: Record<string, string>;
            translations: Translations;
            navigation: NavigationShare;
            workspace: Workspace;
            flash: {
                success: string | null;
                error: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
