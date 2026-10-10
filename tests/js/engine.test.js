import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach } from 'vitest';
import { openUserDb, purgeOtherUsers, purgeAll, dbName } from '../../resources/js/offline/db.js';
import { SyncEngine } from '../../resources/js/offline/engine.js';
import { NetworkError, AuthRequired } from '../../resources/js/offline/api.js';

let userCounter = 0;

function fakeApi(handlers = {}) {
    const calls = { push: [], pull: [] };
    return {
        calls,
        push: async (body) => { calls.push.push(body); return handlers.push ? handlers.push(body) : { wipe: false, results: body.operations.map((o) => ({ op_uuid: o.op_uuid, status: 'applied' })) }; },
        pull: async (body) => { calls.pull.push(body); return handlers.pull ? handlers.pull(body) : pullPayload(); },
    };
}

function pullPayload(overrides = {}) {
    return {
        wipe: false, server_time: new Date().toISOString(), valid_until: new Date(Date.now() + 3600e3).toISOString(),
        user: { id: 1, name: 'Test' }, horse_ids: [1], horses: [{ id: 1, official_name: 'TORNADE', permissions: ['horse.view'], writable: true }],
        sessions: [], events: [], treatments: [], feeding: [], exercises: [{ id: 5, name: 'Cercles' }], open_conflicts: 0, ...overrides,
    };
}

async function engine(api, opts = {}) {
    const db = await openUserDb(opts.userId ?? `u${++userCounter}`);
    const e = new SyncEngine({ db, api, deviceId: 'dev-1', ...opts });
    await e.init();
    return { db, e };
}

describe('SyncEngine', () => {
    it('enregistre l\'opération et la modification locale de façon atomique', async () => {
        const { db, e } = await engine(fakeApi());
        const session = { uuid: 's-1', horse_id: 1, items: [], comments: [] };
        await e.enqueue('session', 'create', { uuid: 's-1', horse_id: 1 }, ['sessions'], (tx) => tx.objectStore('sessions').put(session));
        expect(await db.get('sessions', 's-1')).toBeTruthy();
        const ops = await e.pendingOps();
        expect(ops).toHaveLength(1);
        expect(ops[0].op_uuid).toMatch(/^[0-9a-f-]{36}$/);
        expect(e.state.pending).toBe(1);
    });

    it('conserve la file d\'attente après « fermeture » de l\'application', async () => {
        const userId = 'persist';
        const first = await engine(fakeApi(), { userId });
        await first.e.enqueue('daily_log', 'create', { uuid: 'l-1', horse_id: 1 });
        first.db.close();
        const second = await engine(fakeApi(), { userId });
        expect(await second.e.pendingOps()).toHaveLength(1);
        expect(second.e.state.pending).toBe(1);
    });

    it('vide la file uniquement pour les opérations confirmées par le serveur', async () => {
        const api = fakeApi({
            push: (body) => ({ wipe: false, results: [
                { op_uuid: body.operations[0].op_uuid, status: 'applied' },
                { op_uuid: body.operations[1].op_uuid, status: 'error', message: 'temporaire' },
                { op_uuid: body.operations[2].op_uuid, status: 'rejected', message: 'Accès révoqué' },
                { op_uuid: body.operations[3].op_uuid, status: 'conflict', conflict_id: 9 },
            ] }),
        });
        const { db, e } = await engine(api);
        for (let i = 0; i < 4; i++) await e.enqueue('comment', 'create', { uuid: `c-${i}`, body: 'x' });
        await e.push();
        const remaining = await e.pendingOps();
        expect(remaining).toHaveLength(1);
        expect(remaining[0].status).toBe('error');
        expect(remaining[0].attempts).toBe(1);
        const rejected = await db.getAll('rejected');
        expect(rejected).toHaveLength(1);
        expect(rejected[0].payload.uuid).toBe('c-2'); // saisie refusée conservée, jamais perdue silencieusement
        expect(e.state.conflicts).toBe(1);
    });

    it('envoie les opérations dans l\'ordre de création', async () => {
        const api = fakeApi();
        const { e } = await engine(api);
        await e.enqueue('session', 'create', { uuid: 'a' });
        await e.enqueue('session_exercise', 'update', { uuid: 'b' });
        await e.enqueue('session', 'complete', { uuid: 'a' });
        await e.push();
        expect(api.calls.push[0].operations.map((o) => o.action)).toEqual(['create', 'update', 'complete']);
    });

    it('garde les données en cas d\'échec réseau et signale l\'erreur', async () => {
        const api = fakeApi({ push: () => { throw new NetworkError('offline'); } });
        const { e } = await engine(api);
        await e.enqueue('daily_log', 'create', { uuid: 'l-1' });
        expect(await e.sync()).toBe('error');
        expect(await e.pendingOps()).toHaveLength(1);
        expect(e.state.error).toMatch(/Connexion indisponible/);
    });

    it('demande une reconnexion sans perdre les saisies si la session a expiré', async () => {
        const api = fakeApi({ push: () => { throw new AuthRequired('expired'); } });
        const { e } = await engine(api);
        await e.enqueue('daily_log', 'create', { uuid: 'l-1' });
        await e.sync();
        expect(e.state.authRequired).toBe(true);
        expect(await e.pendingOps()).toHaveLength(1);
    });

    it('le pull purge les chevaux et séances dont l\'accès a été retiré', async () => {
        let payload = pullPayload({ horse_ids: [1, 2], horses: [{ id: 1, official_name: 'A' }, { id: 2, official_name: 'B' }], sessions: [{ uuid: 's-2', horse_id: 2, items: [], comments: [] }] });
        const api = fakeApi({ pull: () => payload });
        const { db, e } = await engine(api);
        await e.sync();
        expect(await db.count('horses')).toBe(2);
        expect(await db.get('sessions', 's-2')).toBeTruthy();

        payload = pullPayload({ horse_ids: [1], horses: [{ id: 1, official_name: 'A' }] });
        await e.sync();
        expect(await db.count('horses')).toBe(1);
        expect(await db.get('sessions', 's-2')).toBeUndefined();
    });

    it('une séance avec modifications en attente garde sa version locale lors du pull', async () => {
        const api = fakeApi({
            push: () => { throw new NetworkError('x'); },
            pull: () => pullPayload({ sessions: [{ uuid: 's-1', horse_id: 1, status: 'planned', items: [], comments: [] }] }),
        });
        const { db, e } = await engine(api);
        await e.enqueue('session', 'complete', { uuid: 's-1' }, ['sessions'], (tx) => tx.objectStore('sessions').put({ uuid: 's-1', horse_id: 1, status: 'completed', items: [], comments: [] }));
        await e.pull();
        expect((await db.get('sessions', 's-1')).status).toBe('completed');
    });

    it('purge les données expirées mais conserve la file d\'attente', async () => {
        let now = Date.now();
        const api = fakeApi();
        const { db, e } = await engine(api, { now: () => now });
        await e.sync();
        await e.enqueue('daily_log', 'create', { uuid: 'l-1' });
        now += 2 * 3600e3; // au-delà de valid_until
        expect(await e.checkExpiry()).toBe(true);
        expect(await db.count('horses')).toBe(0);
        expect(await e.pendingOps()).toHaveLength(1);
    });

    it('déclenche la purge à distance demandée par le serveur', async () => {
        let wiped = false;
        const api = fakeApi({ pull: () => ({ wipe: true }) });
        const { e } = await engine(api, { onWipe: async () => { wiped = true; } });
        expect(await e.sync()).toBe('wiped');
        expect(wiped).toBe(true);
    });

    it('ne lance pas deux synchronisations simultanées', async () => {
        const api = fakeApi();
        const { e } = await engine(api);
        await Promise.all([e.sync(), e.sync(), e.sync()]);
        expect(api.calls.pull).toHaveLength(1);
    });
});

describe('Séparation des comptes sur un même appareil', () => {
    it('supprime les bases des autres utilisateurs', async () => {
        const a = await openUserDb('alice');
        await a.put('horses', { id: 1, official_name: 'Privé' });
        a.close();
        await purgeOtherUsers('bob');
        const dbs = await indexedDB.databases();
        expect(dbs.map((d) => d.name)).not.toContain(dbName('alice'));
    });

    it('purgeAll efface toutes les données locales', async () => {
        const a = await openUserDb('zoe');
        a.close();
        await purgeAll();
        const dbs = await indexedDB.databases();
        expect(dbs.filter((d) => d.name.startsWith('equilibre-'))).toHaveLength(0);
    });
});
