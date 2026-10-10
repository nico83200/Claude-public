// Stockage local IndexedDB, une base distincte par utilisateur.
// Aucun secret d'authentification n'est stocké ici (session par cookie HttpOnly).
import { openDB, deleteDB } from 'idb';

export const STORES = ['meta', 'horses', 'sessions', 'exercises', 'events', 'treatments', 'feeding', 'queue', 'rejected'];
const REGISTRY = 'equilibre-registry';

export function dbName(userId) {
    return `equilibre-u${userId}`;
}

async function registry() {
    const db = await openDB(REGISTRY, 1, {
        upgrade(d) {
            d.createObjectStore('users');
        },
        // Ne jamais bloquer une purge demandée par un autre onglet.
        blocking() {
            db.close();
        },
    });
    return db;
}

export async function openUserDb(userId) {
    const reg = await registry();
    await reg.put('users', Date.now(), String(userId));
    reg.close();

    const handle = await openDB(dbName(userId), 1, {
        // Une purge (déconnexion, changement de compte, révocation) ferme la connexion ouverte.
        blocking() {
            handle.close();
        },
        upgrade(db) {
            db.createObjectStore('meta');
            db.createObjectStore('horses', { keyPath: 'id' });
            const sessions = db.createObjectStore('sessions', { keyPath: 'uuid' });
            sessions.createIndex('horse_id', 'horse_id');
            db.createObjectStore('exercises', { keyPath: 'id' });
            db.createObjectStore('events', { keyPath: 'uuid' });
            db.createObjectStore('treatments', { keyPath: 'id' });
            db.createObjectStore('feeding', { keyPath: 'horse_id' });
            const queue = db.createObjectStore('queue', { keyPath: 'op_uuid' });
            queue.createIndex('seq', 'seq');
            db.createObjectStore('rejected', { keyPath: 'op_uuid' });
        },
    });
    return handle;
}

/** Purge toutes les bases locales d'autres utilisateurs (changement de compte). */
export async function purgeOtherUsers(currentUserId) {
    const reg = await registry();
    const keys = await reg.getAllKeys('users');
    for (const key of keys) {
        if (String(key) !== String(currentUserId)) {
            await deleteDB(dbName(key));
            await reg.delete('users', key);
        }
    }
    reg.close();
}

/** Purge complète (déconnexion, révocation, demande de purge à distance). */
export async function purgeAll() {
    const reg = await registry();
    const keys = await reg.getAllKeys('users');
    reg.close();
    for (const key of keys) {
        await deleteDB(dbName(key));
    }
    await deleteDB(REGISTRY);
}

export async function purgeUser(db, userId) {
    db.close();
    await deleteDB(dbName(userId));
}
