import type { NavKey } from '@/types/navigation';
import type { Role } from '@/types/auth';

export type LocaleCode = 'en' | 'fr' | 'rn';

export type BranchPlaceholder = {
    id: number;
    name: string;
};

export type WorkspaceBusiness = {
    id: number;
    name: string;
    plan: string;
    subscription_status: string;
    subscription_ends_at: string | null;
    allows_write_access: boolean;
    country: string;
    currency: string;
};

export type WorkspaceLimits = {
    max_branches: number;
    active_branches: number;
    max_staff: number | null;
    staff_seats: number;
    features: string[];
};

export type ActiveShift = {
    id: number;
    branch_id: number;
    branch_name: string | null;
    clocked_in_at: string | null;
    on_active_branch: boolean;
};

export type ActiveCashSession = {
    id: number;
    opening_float_minor: number;
    opened_at: string | null;
};

export type Workspace = {
    business: WorkspaceBusiness | null;
    branch: BranchPlaceholder | null;
    branches: BranchPlaceholder[];
    limits: WorkspaceLimits | null;
    active_shift: ActiveShift | null;
    active_cash_session: ActiveCashSession | null;
    shift_required: boolean;
    outside_hours: boolean;
    needs_onboarding: boolean;
};

export type NavigationShare = {
    roleMap: Record<NavKey, Role[]>;
    allowedKeys: NavKey[];
};

export type Translations = {
    brand: {
        name: string;
        tagline: string;
    };
    nav: Record<string, string>;
    topbar: Record<string, string>;
    pages: Record<
        string,
        {
            title: string;
            description?: string;
            empty_title?: string;
            empty_description?: string;
        }
    >;
    states: {
        loading: { title: string; description: string };
        empty: { title: string; description: string };
        error: { title: string; description: string };
        unauthorized: { title: string; description: string };
    };
    welcome: {
        title: string;
        subtitle: string;
        cta_primary: string;
        cta_login: string;
        cta_register: string;
    };
};
