<?php

namespace App\Support;

use App\Enums\BusinessRole;
use App\Models\Business;

final class Navigation
{
    /**
     * Role keys allowed to see each shell destination.
     *
     * When auth.role is null (pre-tenancy scaffolding), the frontend shows
     * the full tenant shell so local UI work can proceed without fake data.
     *
     * @return array<string, list<string>>
     */
    public static function roleMap(): array
    {
        $tenantRoles = ['owner', 'manager', 'cashier', 'inventory_clerk'];
        $catalogRoles = ['owner', 'manager', 'inventory_clerk'];
        $financeRoles = ['owner', 'manager'];
        $adminRoles = ['owner', 'manager'];
        $ownerOnly = ['owner'];
        $expenseRoles = ['owner', 'manager', 'inventory_clerk'];

        return [
            'dashboard' => $tenantRoles,
            'products' => $catalogRoles,
            'categories' => $catalogRoles,
            'inventory' => $catalogRoles,
            'transfers' => $catalogRoles,
            'sales' => ['owner', 'manager', 'cashier'],
            'customers' => ['owner', 'manager', 'cashier'],
            'expenses' => $expenseRoles,
            'suppliers' => $catalogRoles,
            'reports' => $financeRoles,
            'staff' => $adminRoles,
            'shifts' => $ownerOnly,
            'branches' => $ownerOnly,
            'subscription' => $ownerOnly,
            'platform_subscriptions' => ['platform_super_admin'],
            'platform_payment_instructions' => ['platform_super_admin'],
            'settings' => [...$tenantRoles, 'platform_super_admin'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keysForRole(?string $role, ?Business $business = null): array
    {
        $map = self::roleMap();

        if ($role === null) {
            return [];
        }

        $keys = array_values(array_filter(
            array_keys($map),
            static fn (string $key): bool => in_array($role, $map[$key], true),
        ));

        if (
            $role === BusinessRole::Cashier->value
            && $business?->cashiers_can_log_expenses
            && ! in_array('expenses', $keys, true)
        ) {
            $keys[] = 'expenses';
        }

        return $keys;
    }
}
