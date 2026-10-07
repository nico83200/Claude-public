/* Service worker : page hors ligne et mise en cache des images.
 * Feuilles de style et scripts : toujours le réseau d'abord, pour qu'une mise à jour s'applique immédiatement. */
const VERSION = 'cmd-1.7.2';
const STATIC = ['offline.html', 'assets/icons/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  // Les caches des versions précédentes (dont d'anciennes feuilles de style) sont supprimés
  e.waitUntil((async () => {
    const old = (await caches.keys()).filter((k) => k !== VERSION);
    await Promise.all(old.map((k) => caches.delete(k)));
    await self.clients.claim();
    // Remplacement d'une ancienne version : les pages ouvertes sont rechargées avec les nouveaux styles et scripts
    if (old.length) {
      (await self.clients.matchAll({ type: 'window' })).forEach((c) => c.navigate(c.url).catch(() => {}));
    }
  })());
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  const url = new URL(req.url);
  // Styles et scripts : réseau d'abord (adresse exacte, avec son n° de version), cache seulement hors ligne
  if (/\.(css|js)$/.test(url.pathname) && url.pathname.includes('/assets/')) {
    e.respondWith(fetch(req).then((r) => {
      if (r.ok) { const copy = r.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return r;
    }).catch(() => caches.match(req)));
    return;
  }
  // Images et photos : cache d'abord, rafraîchi en arrière-plan
  if (url.pathname.includes('/assets/') || url.pathname.includes('/uploads/')) {
    e.respondWith(caches.open(VERSION).then(async (c) => {
      const hit = await c.match(req);
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
