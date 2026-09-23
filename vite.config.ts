import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

const lanHost = process.env.KOSPAL_LAN_HOST?.trim() || undefined;
const vitePort = Number(process.env.KOSPAL_LAN_VITE_PORT || 5173);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Fraunces', {
                    weights: [500, 600, 700],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    // Prefer IPv4 so Tauri WebView2 / Windows can load HMR assets.
    // `npm run dev:lan` sets KOSPAL_LAN_HOST so phones can load HMR (default stays localhost).
    // Ignore Rust build output — watching locked DLLs under src-tauri/target
    // crashes Vite with EBUSY on Windows during `tauri:dev`.
    server: {
        host: lanHost ? '0.0.0.0' : '127.0.0.1',
        port: vitePort,
        strictPort: true,
        ...(lanHost
            ? {
                  cors: true,
                  origin: `http://${lanHost}:${vitePort}`,
                  allowedHosts: true,
                  hmr: {
                      host: lanHost,
                      port: vitePort,
                      clientPort: vitePort,
                  },
              }
            : {
                  hmr: {
                      host: '127.0.0.1',
                      port: vitePort,
                  },
              }),
        watch: {
            ignored: [
                '**/src-tauri/target/**',
                '**/src-tauri/gen/**',
                '**/vendor/**',
                '**/storage/**',
                '**/node_modules/**',
                '**/.git/**',
            ],
        },
    },
});
