<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves a standalone web app manifest', function () {
    $response = $this->get(route('pwa.manifest'));

    $response
        ->assertOk()
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/dashboard')
        ->assertJsonPath('background_color', '#1F5C4A')
        ->assertJsonPath('theme_color', '#1F5C4A')
        ->assertJsonPath('shortcuts.0.url', '/sales/pos');

    expect($response->headers->get('content-type'))
        ->toContain('application/manifest+json')
        ->and($response->json('icons'))->toBeArray()
        ->and($response->json('icons.0.src'))->toBe('/pwa/icon-192.png')
        ->and($response->json('icons.0.src'))->not->toStartWith('http')
        ->and($response->json('icons.2.purpose'))->toBe('maskable');
});

it('serves a root-scoped service worker', function () {
    $response = $this->get(route('pwa.service-worker'));

    $response->assertOk();

    expect($response->headers->get('content-type'))
        ->toContain('javascript')
        ->and($response->headers->get('Service-Worker-Allowed'))->toBe('/')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->getContent())->toContain('skipWaiting');
});

it('includes installable app metadata on html pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('viewport-fit=cover', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee('href="/manifest.webmanifest"', false)
        ->assertSee('kospal-boot-splash', false);
});

it('ships png app icons for home-screen install', function (string $path) {
    $fullPath = public_path($path);

    expect(is_file($fullPath))->toBeTrue()
        ->and(substr((string) file_get_contents($fullPath), 0, 8))
        ->toBe("\x89PNG\r\n\x1a\n");
})->with([
    'pwa/icon-192.png',
    'pwa/icon-512.png',
    'pwa/icon-maskable-192.png',
    'pwa/icon-maskable-512.png',
    'apple-touch-icon.png',
]);
