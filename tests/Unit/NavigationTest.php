<?php

use App\Support\Navigation;

it('returns no navigation keys when role is null', function () {
    expect(Navigation::keysForRole(null))->toBe([]);
});

it('limits cashier navigation', function () {
    $keys = Navigation::keysForRole('cashier');

    expect($keys)->toContain('dashboard', 'sales', 'customers')
        ->and($keys)->not->toContain('subscription', 'branches', 'expenses', 'staff');
});

it('limits inventory clerk navigation', function () {
    $keys = Navigation::keysForRole('inventory_clerk');

    expect($keys)->toContain(
        'products',
        'categories',
        'inventory',
        'transfers',
        'purchase-orders',
        'goods-received',
        'supplier-invoices',
        'stock-counts',
        'inventory-timeline',
        'inventory-valuation',
        'suppliers',
    )
        ->and($keys)->not->toContain('sales', 'customers', 'subscription', 'staff', 'supplier-payments');
});

it('excludes catalog destinations for cashiers', function () {
    $keys = Navigation::keysForRole('cashier');

    expect($keys)->not->toContain('products', 'categories', 'inventory', 'transfers', 'suppliers');
});

it('allows owners to see organization admin destinations', function () {
    config(['deployment.mode' => 'web']);

    $keys = Navigation::keysForRole('owner');

    expect($keys)->toContain('branches', 'subscription', 'staff', 'reports');
});

it('exposes platform admin subscription destinations in web mode', function () {
    config(['deployment.mode' => 'web']);

    $keys = Navigation::keysForRole('platform_super_admin');

    expect($keys)->toContain(
        'platform_subscriptions',
        'platform_payment_instructions',
        'settings',
    )->and($keys)->not->toContain('dashboard', 'subscription', 'products');
});

it('hides platform destinations in desktop mode', function () {
    config(['deployment.mode' => 'desktop']);

    $keys = Navigation::keysForRole('platform_super_admin');

    expect($keys)->not->toContain(
        'platform_subscriptions',
        'platform_payment_instructions',
    )->and($keys)->toContain('settings');
});
