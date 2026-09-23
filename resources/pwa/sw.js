const CACHE_VERSION = 'kospal-pwa-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_VERSION)
                        .map((key) => caches.delete(key)),
                ),
            ),
        ]),
    );
});

self.addEventListener('fetch', () => {
    // Network-only. Laravel/Inertia responses include CSRF tokens and
    // session state that must not be served from a stale cache.
});
