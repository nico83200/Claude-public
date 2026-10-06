/* Service worker : mise en cache des ressources statiques et page hors ligne. */
const VERSION = 'cmd-1.3.0';
const STATIC = ['offline.html', 'assets/css/app.css', 'assets/js/app.js', 'assets/icons/icon-192.png', 'assets/vendor/zxing/zxing.min.js'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  const url = new URL(req.url);
  // Ressources statiques : cache d'abord, mise à jour en arrière-plan
  if (url.pathname.includes('/assets/') || url.pathname.includes('/uploads/')) {
    e.respondWith(caches.open(VERSION).then(async (c) => {
      const hit = await c.match(req, { ignoreSearch: true });
      const net = fetch(req).then((r) => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => hit);
      return hit || net;
    }));
    return;
  }
  // Pages : toujours le réseau (données à jour), page hors ligne en secours
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
  }
});
