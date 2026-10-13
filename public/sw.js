/* Service Worker — Jack&Cie
 * Rôle minimal : permettre l'installation de l'application et afficher une page
 * « Pas de connexion » quand le réseau manque. Aucune donnée n'est conservée
 * dans le téléphone : seules les ressources publiques (icônes, styles) sont en cache.
 * La mise à jour v6 efface les caches de l'ancien mode hors ligne.
 * v7 : affichage des notifications (Web Push) et ouverture de la bonne page au toucher.
 * v8 : accusé de réception (diagnostic), installation qui ne bloque plus si une ressource manque.
 * v9 : version communiquée à la page (diagnostic de l'appareil).
 * v10 : manifeste construit par le serveur (jamais mis en cache).
 * v11 : fiches d'urgence consultables sans réseau (contenu public par nature) ;
 *       la page « Pas de connexion » permet de noter une consigne, envoyée au retour du réseau.
 */
const VERSION = 'v11';
const STATIC_CACHE = `jackcie-static-${VERSION}`;
const EMERGENCY_CACHE = 'jackcie-emergency';
const PRECACHE = ['/icons/icon-192.png', '/icons/icon-512.png', '/icons/apple-touch-icon.png', '/icons/favicon-32.png', '/brand/jackcie-wordmark.png', '/brand/jackcie-wordmark.webp', '/offline.html'];

self.addEventListener('install', (event) => {
    // Une ressource manquante ne doit pas empêcher l'installation (sinon l'ancien service worker reste en place).
    event.waitUntil(caches.open(STATIC_CACHE).then((c) => Promise.all(PRECACHE.map((u) => c.add(u).catch(() => null)))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== STATIC_CACHE && k !== EMERGENCY_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Ressources publiques versionnées : cache d'abord.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/brand/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(STATIC_CACHE).then((c) => c.put(req, res.clone()));
            return res;
        })));
        return;
    }

    // Fiche d'urgence (QR code) : réseau d'abord, copie gardée pour l'écurie sans réseau.
    if (/^\/urgence\/[A-Za-z0-9]{40}$/.test(url.pathname)) {
        event.respondWith(fetch(req).then((res) => {
            if (res.ok) caches.open(EMERGENCY_CACHE).then((c) => c.put(url.pathname, res.clone()));
            else if (res.status === 404) caches.open(EMERGENCY_CACHE).then((c) => c.delete(url.pathname));
            return res;
        }).catch(() => caches.match(url.pathname, { cacheName: EMERGENCY_CACHE }).then((hit) => hit || caches.match('/offline.html'))));
        return;
    }

    // Pages : toujours le réseau ; sans connexion, page d'information.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    }
});

// Version demandée par la page (diagnostic des notifications).
self.addEventListener('message', (event) => {
    if (event.data === 'version' && event.ports[0]) event.ports[0].postMessage(VERSION);
    // Fiche d'urgence ouverte par son propriétaire : copie gardée pour consultation sans réseau.
    if (event.data?.type === 'cache-emergency' && /^\/urgence\/[A-Za-z0-9]{40}$/.test(event.data.path || '')) {
        event.waitUntil(fetch(event.data.path, { credentials: 'omit' }).then((res) => res.ok && caches.open(EMERGENCY_CACHE).then((c) => c.put(event.data.path, res))).catch(() => null));
    }
    if (event.data?.type === 'forget-emergency' && event.data.path) {
        event.waitUntil(caches.open(EMERGENCY_CACHE).then((c) => c.delete(event.data.path)));
    }
});

// --- Notifications ------------------------------------------------------------
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data?.text() }; }
    const title = data.title || 'Jack&Cie';
    const receipt = (error) => self.registration.pushManager.getSubscription()
        .then((sub) => sub && fetch('/push/recu', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ endpoint: sub.endpoint, error: error || null, version: VERSION }) }))
        .catch(() => null);
    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { url: data.url || '/dashboard' },
    }).then(() => receipt(null), (e) => receipt(String((e && e.message) || e).slice(0, 200))));
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
