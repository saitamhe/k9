/* K9 SAR — Service Worker para la web admin (scope /)
 *
 * Estrategia:
 *   - Tiles de OSM:   stale-while-revalidate.
 *   - Assets de CDN (unpkg, leaflet):  cache-first.
 *   - Mismo origen no /api ni /field:  network-first con fallback a cache (HTML, CSS, JS).
 *   - /api/*: nunca cacheamos (datos vivos, auth-sensible).
 *   - /field/*: no interceptamos; tiene su propio SW con scope /field/.
 *   - /login y /logout: red directa.
 *
 * Importante: NUNCA guardamos en cache responses redirected ni con type
 * 'opaqueredirect', porque después de un logout el server hace 302 a /login
 * y eso contaminaría el cache de '/'.
 *
 * El client (layouts/app.blade.php) limpia caches al hacer submit del logout.
 */

const CACHE_NAME = 'k9-sar-v2';

const APP_SHELL = [
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

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'logout' || data.type === 'clear-caches') {
        event.waitUntil(
            caches.keys().then((names) =>
                Promise.all(names.filter((n) => n.startsWith('k9-sar-')).map((n) => caches.delete(n)))
            )
        );
    }
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

function isCacheable(res) {
    return res && res.ok && !res.redirected && res.type !== 'opaqueredirect';
}

async function networkFirst(req) {
    try {
        const fresh = await fetch(req);
        if (isCacheable(fresh)) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(req, fresh.clone());
        }
        return fresh;
    } catch (e) {
        const cached = await caches.match(req);
        if (cached) return cached;
        return new Response('offline', { status: 503, statusText: 'Offline' });
    }
}

async function cacheFirst(req) {
    const cached = await caches.match(req);
    if (cached) return cached;
    try {
        const fresh = await fetch(req);
        if (isCacheable(fresh)) {
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
        if (isCacheable(res)) cache.put(req, res.clone());
        return res;
    }).catch(() => null);
    return cached || networkPromise || new Response('offline', { status: 503 });
}
