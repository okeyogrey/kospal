import type { NavItem, NavKey } from '@/types';
import type { Role } from '@/types';

export function isNavKeyAllowed(
    key: NavKey,
    allowedKeys: NavKey[],
    _role: Role | null,
): boolean {
    return allowedKeys.includes(key);
}

export function filterNavItems(
    items: NavItem[],
    allowedKeys: NavKey[],
    role: Role | null,
): NavItem[] {
    return items.filter((item) => {
        if (!item.key) {
            return true;
        }

        return isNavKeyAllowed(item.key, allowedKeys, role);
    });
}
