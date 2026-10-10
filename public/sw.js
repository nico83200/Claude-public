/* Service Worker — Equilibre
 * - Ressources statiques compilées : cache d'abord (noms versionnés).
 * - Navigation : réseau d'abord ; hors connexion, repli sur la coquille
 *   « mode écurie » (/hors-ligne) qui lit IndexedDB. Les autres pages
 *   authentifiées ne sont JAMAIS mises en cache (données privées).
 * - API de synchronisation : réseau uniquement.
 */
const VERSION = 'v1';
const STATIC_CACHE = `equilibre-static-${VERSION}`;
const SHELL_CACHE = `equilibre-shell-${VERSION}`;
const SHELL_URL = '/hors-ligne';
const PRECACHE = ['/manifest.webmanifest', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => ![STATIC_CACHE, SHELL_CACHE].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    const msg = event.data || {};
    if (msg.type === 'cache-assets' && Array.isArray(msg.assets)) {
        event.waitUntil(caches.open(STATIC_CACHE).then((c) => Promise.all(msg.assets.map((u) => c.match(u).then((hit) => hit || c.add(u).catch(() => null))))));
    }
    if (msg.type === 'cache-shell') {
        event.waitUntil(refreshShell());
    }
    if (msg.type === 'purge') {
        event.waitUntil(caches.delete(SHELL_CACHE));
    }
});

async function refreshShell() {
    try {
        const res = await fetch(SHELL_URL, { credentials: 'same-origin', redirect: 'manual' });
        if (res.ok && res.type === 'basic') {
            const cache = await caches.open(SHELL_CACHE);
            await cache.put(SHELL_URL, res.clone());
        }
    } catch { /* hors ligne */ }
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/sync/') || url.pathname.startsWith('/stripe/')) return; // réseau uniquement

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(STATIC_CACHE).then((c) => c.put(req, res.clone()));
            return res;
        })));
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const res = await fetch(req);
                if (url.pathname === SHELL_URL && res.ok && !res.redirected) {
                    const cache = await caches.open(SHELL_CACHE);
                    cache.put(SHELL_URL, res.clone());
                }
                return res;
            } catch {
                const shell = await caches.match(SHELL_URL, { cacheName: SHELL_CACHE });
                return shell || caches.match('/offline.html');
            }
        })());
    }
});
