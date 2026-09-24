<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="theme-color" content="#1F5C4A">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'KOSPAL') }}">
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }

                var standalone = window.navigator.standalone === true
                    || window.matchMedia('(display-mode: standalone)').matches
                    || window.matchMedia('(display-mode: fullscreen)').matches
                    || window.matchMedia('(display-mode: minimal-ui)').matches;

                if (standalone) {
                    document.documentElement.classList.add('kospal-standalone');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.985 0.008 165);
            }

            html.dark {
                background-color: oklch(0.18 0.025 165);
            }

            #kospal-boot-splash {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 2147483647;
                align-items: center;
                justify-content: center;
                background: #000000;
            }

            html.kospal-standalone:not(.kospal-booted) #kospal-boot-splash {
                display: flex;
            }

            @media (display-mode: standalone), (display-mode: fullscreen), (display-mode: minimal-ui) {
                html:not(.kospal-booted) #kospal-boot-splash {
                    display: flex;
                }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'KOSPAL') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="kospal-boot-splash" role="presentation">
            <img src="/brand/logo.png" alt="" width="280" height="280" draggable="false">
        </div>
        <x-inertia::app />
        <script>
            window.setTimeout(function () {
                document.documentElement.classList.add('kospal-booted');
            }, 2500);
        </script>
    </body>
</html>
