/* K9 SAR — Service Worker para la web admin (scope /)
 *
 * Estrategia:
 *   - Tiles de OSM:   stale-while-revalidate.
 *   - Assets de CDN (unpkg, leaflet):  cache-first.
 *   - Mismo origen no /api ni /field:   network-first con fallback a cache (HTML, CSS, JS, fuentes).
 *   - /api/*: nunca cacheamos (datos vivos, auth-sensible).
 *   - /field/*: no interceptamos; tiene su propio SW con scope /field/.
 *   - /login y /logout: red directa.
 */

const CACHE_NAME = 'k9-sar-v1';

const APP_SHELL = [
    '/',
    '/manifest.webmanifest',
    '/icons/icon.svg',
    '/icons/icon-maskable.svg',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) =>
            Promise.all(APP_SHELL.map((url) =>
                cache.add(url).catch((e) => console.warn('[SW] no se pudo cachear', url, e))
            ))
        )
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names
                    .filter((n) => n.startsWith('k9-sar-') && n !== CACHE_NAME)
                    .map((n) => caches.delete(n))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // No tocar la PWA de campo (tiene su propio SW con scope /field/).
    if (url.pathname.startsWith('/field')) return;
    // Endpoints API: red directa, sin caching.
    if (url.pathname.startsWith('/api/')) return;
    // Auth: red directa.
    if (url.pathname === '/login' || url.pathname === '/logout') return;

    // Tiles de OSM: stale-while-revalidate.
    if (url.hostname.endsWith('tile.openstreetmap.org')) {
        event.respondWith(staleWhileRevalidate(req));
        return;
    }

    // Assets desde CDNs conocidos: cache-first.
    if (url.hostname === 'unpkg.com') {
        event.respondWith(cacheFirst(req));
        return;
    }

    // Mismo origen: network-first con fallback a cache.
    if (url.origin === self.location.origin) {
        event.respondWith(networkFirst(req));
        return;
    }
});

async function networkFirst(req) {
    try {
        const fresh = await fetch(req);
        if (fresh && fresh.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(req, fresh.clone());
        }
        return fresh;
    } catch (e) {
        const cached = await caches.match(req);
        if (cached) return cached;
        // Si pidieron HTML y no hay cache, intentamos servir el shell '/'.
        if (req.headers.get('accept')?.includes('text/html')) {
            const fallback = await caches.match('/');
            if (fallback) return fallback;
        }
        return new Response('offline', { status: 503, statusText: 'Offline' });
    }
}

async function cacheFirst(req) {
    const cached = await caches.match(req);
    if (cached) return cached;
    try {
        const fresh = await fetch(req);
        if (fresh && fresh.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(req, fresh.clone());
        }
        return fresh;
    } catch (e) {
        return new Response('offline', { status: 503, statusText: 'Offline' });
    }
}

async function staleWhileRevalidate(req) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    const networkPromise = fetch(req).then((res) => {
        if (res && res.ok) cache.put(req, res.clone());
        return res;
    }).catch(() => null);
    return cached || networkPromise || new Response('offline', { status: 503 });
}
