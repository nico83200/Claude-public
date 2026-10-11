import Alpine from 'alpinejs';
import { installStore, listenForInstall } from './install.js';
import { sessionPlanner } from './session-planner.js';
import { careNext } from './care-next.js';
import { assistantStore } from './assistant.js';
import { updateUpload } from './update-upload.js';

window.Alpine = Alpine;

Alpine.data('sessionPlanner', sessionPlanner);
Alpine.data('careNext', careNext);
Alpine.data('updateUpload', updateUpload);
const assistantConfig = (() => {
    try { return JSON.parse(document.getElementById('assistant-config')?.textContent || '{}'); } catch { return {}; }
})();
Alpine.store('assistant', assistantStore(assistantConfig));
Alpine.store('install', installStore());
listenForInstall(Alpine);

// État de la connexion (bandeau « Pas de connexion »).
Alpine.store('network', {
    online: navigator.onLine,
    init() {
        window.addEventListener('online', () => { this.online = true; });
        window.addEventListener('offline', () => { this.online = false; });
    },
});

Alpine.start();

// --- Service Worker (installation de l'application, page « pas de connexion ») ---
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => { /* PWA non disponible : l'application reste utilisable */ });
    });
}

// --- Nettoyage de l'ancien mode hors ligne ----------------------------------
// Les versions précédentes gardaient une copie des chevaux dans le téléphone (IndexedDB) :
// elle est effacée une fois pour toutes.
(async () => {
    try {
        if (localStorage.getItem('jackcie-offline-removed')) return;
        const dbs = (await indexedDB.databases?.()) ?? [];
        for (const db of dbs) {
            if (db.name?.startsWith('jackcie')) indexedDB.deleteDatabase(db.name);
        }
        ['jackcie-pending', 'jackcie-user'].forEach((k) => localStorage.removeItem(k));
        localStorage.setItem('jackcie-offline-removed', '1');
    } catch { /* navigateur sans IndexedDB : rien à nettoyer */ }
})();
