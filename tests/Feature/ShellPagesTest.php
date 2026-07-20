<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    ['owner' => $this->user] = $this->createBusinessWithOwner();
});

it('redirects guests from shell pages to login', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'dashboard',
    'products.index',
    'categories.index',
    'inventory.index',
    'inventory.low-stock',
    'inventory.timeline',
    'inventory.valuation',
    'sales.index',
    'sales.pos',
    'customers.index',
    'expenses.index',
    'suppliers.index',
    'reports.index',
    'staff.index',
    'branches.index',
    'license.edit',
    'unauthorized',
]);

it('allows authenticated business members to visit shell pages', function (string $route, string $component) {
    $this->actingAs($this->user)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['dashboard', 'dashboard'],
    ['products.index', 'products/index'],
    ['categories.index', 'categories/index'],
    ['inventory.index', 'inventory/index'],
    ['inventory.low-stock', 'inventory/low-stock'],
    ['inventory.timeline', 'inventory/timeline'],
    ['inventory.valuation', 'inventory/valuation'],
    ['sales.index', 'sales/index'],
    ['sales.pos', 'sales/pos'],
    ['customers.index', 'customers/index'],
    ['expenses.index', 'expenses/index'],
    ['suppliers.index', 'suppliers/index'],
    ['reports.index', 'reports/index'],
    ['staff.index', 'staff/index'],
    ['branches.index', 'branches/index'],
    ['license.edit', 'settings/license'],
    ['unauthorized', 'unauthorized'],
]);

it('shares tenant-aware kospal shell props', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('locale')
            ->has('locales')
            ->has('currencies')
            ->has('translations.nav')
            ->has('navigation.roleMap')
            ->has('navigation.allowedKeys')
            ->has('workspace.branches')
            ->where('auth.role', 'owner')
            ->where('workspace.needs_onboarding', false)
            ->where('workspace.business.name', fn ($name) => is_string($name) && $name !== '')
        );
});
