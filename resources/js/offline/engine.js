// Moteur de synchronisation : file d'attente persistante, push idempotent,
// pull en instantané (les chevaux non autorisés sont purgés localement).
import { uuid } from './uuid.js';
import { AuthRequired, NetworkError } from './api.js';

const BATCH = 50;
const MAX_ATTEMPTS_BEFORE_ALERT = 5;

export class SyncEngine extends EventTarget {
    constructor({ db, api, deviceId, deviceLabel = null, now = () => Date.now(), onWipe = async () => {} }) {
        super();
        this.db = db;
        this.api = api;
        this.deviceId = deviceId;
        this.deviceLabel = deviceLabel;
        this.now = now;
        this.onWipe = onWipe;
        this.state = { syncing: false, pending: 0, rejected: 0, lastSync: null, validUntil: null, error: null, authRequired: false, conflicts: 0, expired: false };
        this._running = null;
    }

    emit() {
        this.dispatchEvent(new CustomEvent('change', { detail: { ...this.state } }));
    }

    async init() {
        const meta = this.db.transaction('meta').objectStore('meta');
        this.state.lastSync = (await meta.get('last_sync')) ?? null;
        this.state.validUntil = (await meta.get('valid_until')) ?? null;
        this.state.conflicts = (await meta.get('open_conflicts')) ?? 0;
        await this.refreshCounts();
        await this.checkExpiry();
        this.emit();
    }

    async refreshCounts() {
        this.state.pending = await this.db.count('queue');
        this.state.rejected = await this.db.count('rejected');
    }

    /** Les données locales ont une durée de validité : au-delà, elles sont purgées (hors file d'attente). */
    async checkExpiry() {
        const until = this.state.validUntil ? Date.parse(this.state.validUntil) : null;
        this.state.expired = until !== null && this.now() > until;
        if (this.state.expired) {
            const tx = this.db.transaction(['horses', 'sessions', 'events', 'treatments', 'feeding'], 'readwrite');
            await Promise.all(['horses', 'sessions', 'events', 'treatments', 'feeding'].map((s) => tx.objectStore(s).clear()));
            await tx.done;
        }
        return this.state.expired;
    }

    /**
     * Enregistre une opération dans la file, dans la même transaction que la
     * modification locale (atomicité en cas de fermeture brutale).
     * `apply(tx)` applique la modification optimiste aux stores concernés.
     */
    async enqueue(entity, action, payload, stores = [], apply = null) {
        const tx = this.db.transaction(['queue', 'meta', ...stores], 'readwrite');
        const meta = tx.objectStore('meta');
        const seq = ((await meta.get('seq')) ?? 0) + 1;
        await meta.put(seq, 'seq');
        const op = { op_uuid: uuid(), seq, entity, action, payload, created_at: new Date(this.now()).toISOString(), status: 'pending', attempts: 0, last_error: null };
        await tx.objectStore('queue').put(op);
        if (apply) await apply(tx);
        await tx.done;
        await this.refreshCounts();
        this.emit();
        return op;
    }

    async pendingOps() {
        return this.db.getAllFromIndex('queue', 'seq');
    }

    async sync() {
        if (this._running) return this._running;
        this._running = this._sync().finally(() => {
            this._running = null;
        });
        return this._running;
    }

    async _sync() {
        this.state.syncing = true;
        this.state.error = null;
        this.emit();
        try {
            const wiped = await this.push();
            if (wiped) return 'wiped';
            const result = await this.pull();
            this.state.authRequired = false;
            return result;
        } catch (e) {
            if (e instanceof AuthRequired) {
                this.state.authRequired = true;
                this.state.error = 'Reconnexion nécessaire pour synchroniser. Vos saisies restent enregistrées sur l\'appareil.';
            } else if (e instanceof NetworkError) {
                this.state.error = 'Connexion indisponible : nouvelle tentative automatique.';
            } else {
                this.state.error = e.message;
            }
            return 'error';
        } finally {
            this.state.syncing = false;
            await this.refreshCounts();
            this.emit();
        }
    }

    /** @returns {Promise<boolean>} true si une purge a été ordonnée */
    async push() {
        const attempted = new Set(); // chaque opération est envoyée au plus une fois par cycle
        for (;;) {
            const ops = (await this.pendingOps()).filter((o) => !attempted.has(o.op_uuid)).slice(0, BATCH);
            ops.forEach((o) => attempted.add(o.op_uuid));
            if (!ops.length) return false;
            const response = await this.api.push({
                device_uuid: this.deviceId,
                operations: ops.map(({ op_uuid, entity, action, payload, created_at }) => ({ op_uuid, entity, action, payload, created_at })),
            });
            if (response.wipe) {
                await this.onWipe();
                return true;
            }
            const byId = new Map(response.results.map((r) => [r.op_uuid, r]));
            const tx = this.db.transaction(['queue', 'rejected', 'meta'], 'readwrite');
            let progressed = false;
            for (const op of ops) {
                const r = byId.get(op.op_uuid);
                if (!r || r.status === 'error') {
                    op.attempts += 1;
                    op.status = 'error';
                    op.last_error = r?.message ?? 'Réponse incomplète';
                    await tx.objectStore('queue').put(op);
                    continue;
                }
                progressed = true;
                await tx.objectStore('queue').delete(op.op_uuid);
                if (r.status === 'rejected') {
                    // Jamais de perte silencieuse : l'opération refusée reste consultable.
                    await tx.objectStore('rejected').put({ ...op, status: 'rejected', last_error: r.message, rejected_at: new Date(this.now()).toISOString() });
                } else if (r.status === 'conflict') {
                    const meta = tx.objectStore('meta');
                    await meta.put(((await meta.get('open_conflicts')) ?? 0) + 1, 'open_conflicts');
                    this.state.conflicts += 1;
                }
            }
            await tx.done;
            if (!progressed) {
                if (ops.some((o) => o.attempts >= MAX_ATTEMPTS_BEFORE_ALERT)) {
                    this.state.error = 'Certaines modifications n\'ont pas pu être envoyées après plusieurs tentatives.';
                }
                return false;
            }
        }
    }

    async pull() {
        const data = await this.api.pull({ device_uuid: this.deviceId, device_label: this.deviceLabel, ack_wipe: false });
        if (data.wipe) {
            await this.onWipe();
            return 'wiped';
        }
        const pending = await this.pendingOps();
        const pendingSessionUuids = new Set(pending.flatMap((op) => [op.payload?.uuid, op.payload?.session_uuid]).filter(Boolean));
        const allowedHorses = new Set(data.horse_ids);

        const stores = ['horses', 'sessions', 'exercises', 'events', 'treatments', 'feeding', 'meta'];
        const tx = this.db.transaction(stores, 'readwrite');
        const localSessions = await tx.objectStore('sessions').getAll();
        for (const s of ['horses', 'exercises', 'events', 'treatments', 'feeding']) await tx.objectStore(s).clear();

        for (const h of data.horses) await tx.objectStore('horses').put(h);
        for (const e of data.exercises) await tx.objectStore('exercises').put(e);
        for (const e of data.events) await tx.objectStore('events').put(e);
        for (const t of data.treatments) await tx.objectStore('treatments').put(t);
        for (const f of data.feeding) await tx.objectStore('feeding').put(f);

        const sessions = tx.objectStore('sessions');
        const serverUuids = new Set(data.sessions.map((s) => s.uuid));
        for (const local of localSessions) {
            const keepPending = pendingSessionUuids.has(local.uuid) && allowedHorses.has(local.horse_id);
            if (!serverUuids.has(local.uuid) && !keepPending) await sessions.delete(local.uuid);
        }
        for (const s of data.sessions) {
            // Une séance avec des modifications locales en attente garde sa version locale.
            if (!pendingSessionUuids.has(s.uuid)) await sessions.put({ ...s, _local: false });
        }
        const meta = tx.objectStore('meta');
        await meta.put(data.server_time, 'last_sync');
        await meta.put(data.valid_until, 'valid_until');
        await meta.put(data.open_conflicts, 'open_conflicts');
        await meta.put(data.user, 'user');
        await tx.done;

        this.state.lastSync = data.server_time;
        this.state.validUntil = data.valid_until;
        this.state.conflicts = data.open_conflicts;
        this.state.expired = false;
        return 'ok';
    }

    async dismissRejected(opUuid) {
        await this.db.delete('rejected', opUuid);
        await this.refreshCounts();
        this.emit();
    }
}
