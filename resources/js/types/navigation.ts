import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { Role } from '@/types/auth';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavKey =
    | 'dashboard'
    | 'products'
    | 'categories'
    | 'brands'
    | 'inventory'
    | 'transfers'
    | 'sales'
    | 'customers'
    | 'expenses'
    | 'suppliers'
    | 'reports'
    | 'staff'
    | 'shifts'
    | 'branches'
    | 'subscription'
    | 'platform_subscriptions'
    | 'platform_payment_instructions'
    | 'settings';

export type NavItem = {
    key?: NavKey;
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    roles?: Role[];
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
