/* Service Worker — Jack&Cie
 * Rôle minimal : permettre l'installation de l'application et afficher une page
 * « Pas de connexion » quand le réseau manque. Aucune donnée n'est conservée
 * dans le téléphone : seules les ressources publiques (icônes, styles) sont en cache.
 * La mise à jour v6 efface les caches de l'ancien mode hors ligne.
 * v7 : affichage des notifications (Web Push) et ouverture de la bonne page au toucher.
 */
const VERSION = 'v7';
const STATIC_CACHE = `jackcie-static-${VERSION}`;
const PRECACHE = ['/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/apple-touch-icon.png', '/icons/favicon-32.png', '/brand/jackcie-wordmark.png', '/brand/jackcie-wordmark.webp', '/offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== STATIC_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Ressources publiques versionnées : cache d'abord.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/brand/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(STATIC_CACHE).then((c) => c.put(req, res.clone()));
            return res;
        })));
        return;
    }

    // Pages : toujours le réseau ; sans connexion, page d'information.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    }
});

// --- Notifications ------------------------------------------------------------
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data?.text() }; }
    const title = data.title || 'Jack&Cie';
    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { url: data.url || '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/dashboard', self.location.origin);
    if (target.origin !== self.location.origin) return; // jamais d'ouverture vers un autre site
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
        for (const w of wins) {
            if (new URL(w.url).origin === target.origin && 'navigate' in w) return w.navigate(target.href).then((c) => (c || w).focus());
        }
        return self.clients.openWindow(target.href);
    }));
});
