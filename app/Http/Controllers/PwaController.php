<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $icon192 = '/pwa/icon-192.png';
        $icon512 = '/pwa/icon-512.png';
        $maskable192 = '/pwa/icon-maskable-192.png';
        $maskable512 = '/pwa/icon-maskable-512.png';

        $name = (string) config('app.name', 'KOSPAL');

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => $name,
            'description' => (string) trans('kospal.brand.tagline'),
            'lang' => app()->getLocale(),
            'dir' => 'ltr',
            'start_url' => route('dashboard', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'prefer_related_applications' => false,
            'orientation' => 'any',
            'background_color' => '#000000',
            'theme_color' => '#1F5C4A',
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $maskable192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => $maskable512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => (string) trans('kospal.pages.pos.title'),
                    'short_name' => (string) trans('kospal.nav.pos'),
                    'url' => route('sales.pos', absolute: false),
                    'icons' => [
                        [
                            'src' => $icon192,
                            'sizes' => '192x192',
                            'type' => 'image/png',
                        ],
                    ],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function serviceWorker(): Response
    {
        $path = resource_path('pwa/sw.js');

        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
