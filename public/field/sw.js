/* K9 Field — Service Worker
 *
 * Cachea el app shell para que la PWA arranque sin red. Solo se registra cuando
 * la PWA se sirve desde HTTPS o localhost (restriccion del navegador).
 *
 * Estrategias:
 *   - App shell (HTML/CSS/JS/Leaflet CDN): cache-first con fallback a red.
 *   - Tiles de OSM: stale-while-revalidate (sirve cache si existe, refresca en
 *     background). El pre-cache real de tiles para offline se hace desde la app
 *     (sprint posterior con leaflet.offline).
 *   - Resto: solo red, sin caching.
 *
 * Versionado: bumpear CACHE_NAME fuerza limpieza del cache viejo al activar.
 */

const CACHE_NAME = 'k9-field-v4';
const APP_SHELL = [
    '/field',
    '/field/styles.css?v=4',
    '/field/app.js?v=4',
    '/field/manifest.json',
    '/field/icons/icon.svg',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://unpkg.com/dexie@4.0.8/dist/dexie.min.js',
    'https://unpkg.com/leaflet.offline@3.1.2/dist/bundle.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) =>
            // addAll falla si CUALQUIER recurso responde !=2xx. Usamos add() en
            // un loop para tolerar fallos individuales (ej. CORS de unpkg).
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
            Promise.all(names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // No interceptamos llamadas al T3 ni al API del VPS: deben ir a la red sin pasar por cache.
    if (url.hostname === '192.168.4.1') return;
    if (url.pathname.startsWith('/api/')) return;

    // Tiles de OSM: stale-while-revalidate
    if (url.hostname.endsWith('tile.openstreetmap.org')) {
        event.respondWith(staleWhileRevalidate(req));
        return;
    }

    // App shell: cache-first con fallback a red
    if (url.pathname.startsWith('/field') || url.hostname === 'unpkg.com') {
        event.respondWith(cacheFirst(req));
        return;
    }
});

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
        // Sin red y sin cache: devolver un Response de error claro
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
