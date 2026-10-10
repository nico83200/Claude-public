import Alpine from 'alpinejs';
import { stableApp } from './offline/app.js';
import { purgeAll, purgeOtherUsers } from './offline/db.js';

window.Alpine = Alpine;

Alpine.data('stableApp', stableApp);

// Indicateur de connexion / synchronisation visible dans l'en-tête.
Alpine.data('syncIndicator', () => ({
    online: navigator.onLine,
    pending: 0,
    status: null,
    init() {
        window.addEventListener('online', () => { this.online = true; });
        window.addEventListener('offline', () => { this.online = false; });
        window.addEventListener('equilibre-sync', (e) => { this.status = e.detail; this.pending = e.detail.pending; });
        try { this.pending = Number(localStorage.getItem('equilibre-pending') ?? 0); } catch { /* ignore */ }
    },
    get label() {
        if (!this.online) return 'Hors ligne';
        if (this.status?.syncing) return 'Synchronisation…';
        if (this.status?.conflicts > 0) return 'Conflit à résoudre';
        if (this.status?.error) return 'Échec de synchronisation';
        if (this.pending > 0) return 'Modifications en attente';
        return 'En ligne';
    },
    get dot() {
        if (!this.online) return 'bg-slate-400';
        if (this.status?.error || this.status?.conflicts > 0) return 'bg-red-500';
        if (this.status?.syncing || this.pending > 0) return 'bg-amber-500';
        return 'bg-emerald-500';
    },
}));

window.addEventListener('equilibre-sync', (e) => {
    try { localStorage.setItem('equilibre-pending', String(e.detail.pending ?? 0)); } catch { /* ignore */ }
});

Alpine.start();

// --- Service Worker -------------------------------------------------------
async function postToWorker(message) {
    const reg = await navigator.serviceWorker?.ready;
    reg?.active?.postMessage(message);
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(() => {
            // Mise en cache des ressources de la page courante (feuilles de style, scripts compilés).
            const assets = [...document.querySelectorAll('link[rel="stylesheet"][href], script[src], link[rel="modulepreload"][href]')]
                .map((el) => el.href || el.src).filter((u) => u.startsWith(location.origin));
            postToWorker({ type: 'cache-assets', assets });
            if (document.body.dataset.offlineShell === '1') postToWorker({ type: 'cache-shell' });
        }).catch(() => { /* PWA non disponible : l'application reste utilisable en ligne */ });
    });
}

// --- Séparation des comptes sur un même appareil --------------------------
const userId = document.querySelector('meta[name="app-user"]')?.content;
if (userId) {
    try {
        const previous = localStorage.getItem('equilibre-user');
        if (previous && previous !== userId) {
            purgeOtherUsers(userId).catch(() => {});
            postToWorker({ type: 'purge' });
            localStorage.removeItem('equilibre-pending');
        }
        localStorage.setItem('equilibre-user', userId);
    } catch { /* stockage indisponible */ }
}

// --- Purge locale à la déconnexion ----------------------------------------
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-logout') || form.dataset.purged) return;
    event.preventDefault();
    let pending = 0;
    try { pending = Number(localStorage.getItem('equilibre-pending') ?? 0); } catch { /* ignore */ }
    if (pending > 0 && !confirm(`${pending} modification(s) hors ligne ne sont pas encore synchronisées et seront perdues. Se déconnecter quand même ?`)) return;
    try {
        await purgeAll();
        await postToWorker({ type: 'purge' });
        localStorage.removeItem('equilibre-pending');
        localStorage.removeItem('equilibre-user');
    } catch { /* on déconnecte quand même */ }
    form.dataset.purged = '1';
    form.submit();
});
