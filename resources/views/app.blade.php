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
                background: #1F5C4A;
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
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
            <svg width="96" height="96" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect width="64" height="64" rx="16" fill="#1F5C4A"/>
                <path d="M18 44V20H30.4C34.9 20 38 22.8 38 27.1C38 30.1 36.3 32.4 33.6 33.3L39.5 44H34.1L28.7 34H22.7V44H18ZM22.7 30.2H29.8C32.1 30.2 33.5 28.8 33.5 26.9C33.5 25 32.1 23.7 29.8 23.7H22.7V30.2Z" fill="#F2D48A"/>
                <circle cx="46" cy="20" r="4" fill="#F2D48A"/>
            </svg>
        </div>
        <x-inertia::app />
        <script>
            window.setTimeout(function () {
                document.documentElement.classList.add('kospal-booted');
            }, 2500);
        </script>
    </body>
</html>
