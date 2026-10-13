/**
 * Consignes notées sans réseau (texte ou voix) : gardées dans le téléphone (IndexedDB « jc-queue »)
 * jusqu'au retour de la connexion, puis envoyées à l'assistant et effacées.
 * Chaque consigne est rattachée au compte qui l'a notée.
 */
const DB = 'jc-queue';
const STORE = 'items';

function open() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function tx(mode, fn) {
    const db = await open();
    return new Promise((resolve, reject) => {
        const t = db.transaction(STORE, mode);
        const result = fn(t.objectStore(STORE));
        t.oncomplete = () => { db.close(); resolve(result?.result ?? result); };
        t.onerror = () => { db.close(); reject(t.error); };
    });
}

export const offlineQueue = {
    available: typeof indexedDB !== 'undefined',
    add(item) { return tx('readwrite', (s) => s.add({ ...item, at: new Date().toISOString() })); },
    remove(id) { return tx('readwrite', (s) => s.delete(id)); },
    async all(userId) {
        const items = await tx('readonly', (s) => s.getAll());
        return (items || []).filter((i) => String(i.userId) === String(userId));
    },
};
