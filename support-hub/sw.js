/* Service worker de la console d'assistance NLapps : application installable, page hors connexion, notifications push. */
const CACHE = 'nlapps-hub-v2';
const SHELL = ['offline.html', 'assets/hub.css', 'assets/icon-192.png', 'assets/nlapps-mark.svg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

// Toujours le réseau (données en direct) ; page d'attente si la connexion est coupée
self.addEventListener('fetch', (e) => {
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match('offline.html')));
  }
});

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: 'Assistance NLapps', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'Assistance NLapps', {
    body: d.body || 'Nouveau message', icon: 'assets/icon-192.png', badge: 'assets/icon-192.png',
    data: { url: d.url || './' }, tag: d.url || 'nlapps', renotify: true,
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = e.notification.data && e.notification.data.url ? e.notification.data.url : './';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    return self.clients.openWindow(url);
  }));
});
