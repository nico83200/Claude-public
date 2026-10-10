// Application « écurie » : fonctionne entièrement à partir d'IndexedDB.
import { openUserDb, purgeAll, purgeOtherUsers } from './db.js';
import { createApi } from './api.js';
import { SyncEngine } from './engine.js';
import { uuid } from './uuid.js';
import { hashPin, verifyPin } from './pin.js';

// Copie profonde compatible avec les proxys réactifs d'Alpine (structuredClone les refuse).
const clone = (o) => JSON.parse(JSON.stringify(o));

const LOCK_AFTER_MS = 15 * 60 * 1000;

export const PHASES = { warmup: 'Échauffement', main: 'Travail principal', complementary: 'Travail complémentaire', cooldown: 'Retour au calme' };
export const TYPES = {
    free: 'Monte libre', private_lesson: 'Cours particulier', group_lesson: 'Cours collectif', groundwork: 'Travail à pied', lunging: 'Longe',
    liberty: 'Liberté', dressage: 'Dressage', jumping: 'Obstacle', poles: 'Barres au sol', ride: 'Balade', outdoor: 'Extérieur',
    recovery: 'Récupération', active_rest: 'Repos actif', other: 'Autre',
};

function deviceId() {
    let id = null;
    try { id = localStorage.getItem('jackcie-device'); } catch { /* stockage indisponible */ }
    if (!id) {
        id = uuid();
        try { localStorage.setItem('jackcie-device', id); } catch { /* ignore */ }
    }
    return id;
}

export function stableApp() {
    return {
        ready: false, error: null, online: navigator.onLine, state: {}, view: 'home', locked: false, pinSet: false, pinInput: '', pinError: null,
        horses: [], exercises: [], horse: null, horseSessions: [], horseEvents: [], horseTreatments: [], horseFeeding: null,
        session: null, pending: [], rejected: [], conflicts: [], conflictsLoaded: false,
        form: {}, search: '', toast: null, phases: PHASES, types: TYPES, lastActivity: Date.now(),

        async init() {
            const userId = document.querySelector('meta[name="app-user"]')?.content;
            let token = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                await purgeOtherUsers(userId); // séparation stricte entre comptes sur un même appareil
                this.db = await openUserDb(userId);
                this.api = createApi({ getToken: () => token, setToken: (t) => { token = t; } });
                this.engine = new SyncEngine({ db: this.db, api: this.api, deviceId: deviceId(), deviceLabel: navigator.userAgent.slice(0, 90), onWipe: async () => { this.db.close(); await purgeAll(); location.reload(); } });
                this.engine.addEventListener('change', (e) => { this.state = e.detail; window.dispatchEvent(new CustomEvent('jackcie-sync', { detail: e.detail })); });
                await this.engine.init();
                this.state = { ...this.engine.state };
                const pin = await this.db.get('meta', 'pin');
                this.pinSet = !!pin;
                this.locked = !!pin;
                await this.load();
                this.ready = true;
                window.addEventListener('online', () => { this.online = true; this.syncNow(); });
                window.addEventListener('offline', () => { this.online = false; });
                ['pointerdown', 'keydown'].forEach((ev) => window.addEventListener(ev, () => { this.lastActivity = Date.now(); }));
                setInterval(() => this.tick(), 30000);
                if (this.online) this.syncNow();
                this.routeFromHash();
            } catch (e) {
                this.error = 'Le stockage local est indisponible sur ce navigateur (navigation privée ?). Le mode hors ligne ne peut pas fonctionner.';
                console.error(e);
            }
        },

        async tick() {
            if (this.pinSet && !this.locked && Date.now() - this.lastActivity > LOCK_AFTER_MS) this.locked = true;
            await this.engine.checkExpiry();
            if (this.online && !this.state.syncing && (this.state.pending > 0 || !this.state.lastSync || Date.now() - Date.parse(this.state.lastSync) > 10 * 60 * 1000)) this.syncNow();
        },

        routeFromHash() {
            if (location.hash === '#pending') this.go('pending');
        },

        async syncNow() {
            const r = await this.engine.sync();
            await this.load();
            if (r === 'ok') this.notify('Synchronisation réussie');
        },

        async load() {
            this.horses = (await this.db.getAll('horses')).sort((a, b) => a.official_name.localeCompare(b.official_name));
            this.exercises = await this.db.getAll('exercises');
            this.pending = await this.engine.pendingOps();
            this.rejected = await this.db.getAll('rejected');
            if (this.horse) {
                this.horse = this.horses.find((h) => h.id === this.horse.id) ?? null;
                if (!this.horse) { this.go('home'); this.notify('L\'accès à ce cheval n\'est plus disponible.'); return; }
                await this.loadHorse(this.horse.id);
            }
            if (this.session) {
                this.session = (await this.db.get('sessions', this.session.uuid)) ?? null;
                if (!this.session && ['session', 'live', 'debrief'].includes(this.view)) this.go('horse');
            }
        },

        async loadHorse(id) {
            this.horseSessions = (await this.db.getAllFromIndex('sessions', 'horse_id', id)).sort((a, b) => b.scheduled_at.localeCompare(a.scheduled_at));
            this.horseEvents = (await this.db.getAll('events')).filter((e) => e.horse_id === id).sort((a, b) => a.starts_at.localeCompare(b.starts_at));
            this.horseTreatments = (await this.db.getAll('treatments')).filter((t) => t.horse_id === id);
            this.horseFeeding = (await this.db.get('feeding', id)) ?? null;
        },

        notify(msg) {
            this.toast = msg;
            setTimeout(() => { if (this.toast === msg) this.toast = null; }, 2500);
        },

        back() {
            const parent = { live: 'session', debrief: 'session', library: 'session', session: 'horse', 'new-session': 'horse', log: 'horse', observation: 'horse', 'horse-notes': 'horse' };
            this.go(parent[this.view] ?? 'home');
        },

        go(view) {
            this.view = view;
            window.scrollTo({ top: 0 });
        },

        can(perm) {
            return !!this.horse && this.horse.writable && this.horse.permissions.includes(perm);
        },

        async openHorse(h) {
            this.horse = h;
            await this.loadHorse(h.id);
            this.go('horse');
        },

        async openSession(s) {
            this.session = await this.db.get('sessions', s.uuid);
            this.go(this.session.status === 'completed' ? 'session' : 'session');
        },

        statusLabel() {
            if (!this.online) return 'Hors ligne';
            if (this.state.syncing) return 'Synchronisation…';
            if (this.state.authRequired) return 'Reconnexion requise';
            if (this.state.error) return 'Échec de synchronisation';
            if (this.state.conflicts > 0) return 'Conflit à résoudre';
            if (this.state.pending > 0) return 'Modifications en attente';
            return 'Synchronisé';
        },

        fmt(iso, withTime = true) {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + (withTime ? ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '');
        },

        isPendingSession(s) {
            return this.pending.some((op) => op.payload?.uuid === s.uuid || op.payload?.session_uuid === s.uuid);
        },

        // ---------------------------------------------------------- Séances
        startNewSession() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            this.form = { scheduled_at: now.toISOString().slice(0, 16), session_type: 'free', objective: '', planned_minutes: 45, location: '', horse_state_before: '', notes: '' };
            this.go('new-session');
        },

        async createSession() {
            const f = this.form;
            const s = {
                uuid: uuid(), horse_id: this.horse.id, version: 1, status: 'planned', rider: this.state.user?.name ?? 'Moi', can_edit: true, _local: true,
                scheduled_at: new Date(f.scheduled_at).toISOString(), session_type: f.session_type, discipline: null, objective: f.objective || null,
                planned_minutes: f.planned_minutes ? Number(f.planned_minutes) : null, location: f.location || null, horse_state_before: f.horse_state_before || null,
                precautions: null, notes: f.notes || null, actual_minutes: null, items: [], comments: [],
            };
            const payload = { ...s };
            delete payload.items; delete payload.comments; delete payload.version; delete payload.status; delete payload.rider; delete payload.can_edit; delete payload._local; delete payload.actual_minutes;
            payload.items = [];
            await this.engine.enqueue('session', 'create', payload, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            this.session = s;
            await this.load();
            this.notify('Séance enregistrée sur l\'appareil');
            this.go('session');
            if (this.online) this.syncNow();
        },

        filteredExercises() {
            const q = this.search.trim().toLowerCase();
            return this.exercises.filter((e) => !q || e.name.toLowerCase().includes(q) || (e.category ?? '').toLowerCase().includes(q)).slice(0, 40);
        },

        async addItem(exercise, phase = 'main', isBreak = false) {
            const item = {
                uuid: uuid(), exercise_id: exercise?.id ?? null, phase, is_break: isBreak, name: isBreak ? 'Pause' : (exercise?.name ?? 'Exercice libre'),
                planned_minutes: exercise?.duration_minutes ?? (isBreak ? 2 : null), planned_repetitions: exercise?.repetitions ?? null,
                instructions: exercise?.instructions ?? null, status: 'pending', done_repetitions: null, actual_minutes: null, difficulty: false, note: null,
                position: (this.session.items.filter((i) => i.phase === phase).length || 0) + 1,
            };
            const s = clone(this.session);
            s.items.push(item);
            await this.engine.enqueue('session_exercise', 'create', { session_uuid: s.uuid, uuid: item.uuid, exercise_id: item.exercise_id, name: item.name, phase, planned_minutes: item.planned_minutes, planned_repetitions: item.planned_repetitions, instructions: item.instructions, is_break: isBreak }, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            this.session = s;
            this.pending = await this.engine.pendingOps();
            this.notify('Ajouté : ' + item.name);
        },

        itemsByPhase(phase) {
            return (this.session?.items ?? []).filter((i) => i.phase === phase);
        },

        /** Modification d'un exercice de séance avec valeurs de base (fusion à trois voies côté serveur). */
        async updateItem(item, changes) {
            const s = clone(this.session);
            const target = s.items.find((i) => i.uuid === item.uuid);
            const base = {};
            for (const k of Object.keys(changes)) base[k] = target[k];
            Object.assign(target, changes);
            await this.engine.enqueue('session_exercise', 'update', { uuid: item.uuid, changes, base }, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            if (s.status === 'planned') {
                s.status = 'in_progress';
                await this.engine.enqueue('session', 'update', { uuid: s.uuid, changes: { status: 'in_progress' }, base: { status: 'planned' } }, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            }
            this.session = s;
            this.pending = await this.engine.pendingOps();
        },

        mark(item, status) { return this.updateItem(item, { status }); },
        reps(item, delta) { return this.updateItem(item, { done_repetitions: Math.max(0, (item.done_repetitions ?? 0) + delta) }); },
        toggleDifficulty(item) { return this.updateItem(item, { difficulty: !item.difficulty }); },
        async noteItem(item) {
            const note = prompt('Observation pour « ' + item.name + ' »', item.note ?? '');
            if (note !== null) await this.updateItem(item, { note: note.slice(0, 255) });
        },
        async minutesItem(item) {
            const v = prompt('Durée réelle (minutes)', item.actual_minutes ?? item.planned_minutes ?? '');
            if (v !== null && v !== '' && !Number.isNaN(Number(v))) await this.updateItem(item, { actual_minutes: Math.max(0, Math.min(300, Number(v))) });
        },

        startDebrief() {
            const s = this.session;
            const done = s.items.filter((i) => i.status === 'done').reduce((t, i) => t + (i.actual_minutes ?? i.planned_minutes ?? 0), 0);
            this.form = { actual_minutes: s.actual_minutes ?? (done || s.planned_minutes), rider_feeling: 3, horse_behavior: 3, concentration: 3, availability: 3, difficulties: '', progress: '', to_rework: '', anomalies: '', next_objectives: '' };
            this.go('debrief');
        },

        async saveDebrief() {
            const s = clone(this.session);
            const debrief = {};
            for (const [k, v] of Object.entries(this.form)) debrief[k] = v === '' ? null : (['actual_minutes', 'rider_feeling', 'horse_behavior', 'concentration', 'availability'].includes(k) ? Number(v) : v);
            Object.assign(s, debrief, { status: 'completed' });
            await this.engine.enqueue('session', 'complete', { uuid: s.uuid, debrief, base_version: s.version }, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            this.session = s;
            await this.load();
            this.notify('Bilan enregistré');
            this.go('session');
            if (this.online) this.syncNow();
        },

        async addComment() {
            const body = (this.form.comment ?? '').trim();
            if (!body) return;
            const s = clone(this.session);
            const c = { uuid: uuid(), body, author: this.state.user?.name ?? 'Moi', created_at: new Date().toISOString() };
            s.comments.push(c);
            await this.engine.enqueue('comment', 'create', { uuid: c.uuid, session_uuid: s.uuid, body }, ['sessions'], (tx) => tx.objectStore('sessions').put(s));
            this.session = s;
            this.form.comment = '';
            this.pending = await this.engine.pendingOps();
        },

        // ---------------------------------------------------------- Suivi quotidien / observations / fiche
        startLog() {
            this.form = { appetite: 'good', general_state: 'good', behavior: '', activity: '', observations: '', anomalies: '' };
            this.go('log');
        },

        async saveLog() {
            const payload = { uuid: uuid(), horse_id: this.horse.id, logged_at: new Date().toISOString(), ...Object.fromEntries(Object.entries(this.form).map(([k, v]) => [k, v === '' ? null : v])) };
            await this.engine.enqueue('daily_log', 'create', payload);
            await this.load();
            this.notify('Suivi enregistré');
            this.go('horse');
            if (this.online) this.syncNow();
        },

        startObservation() {
            this.form = { body: '', severity: 'info' };
            this.go('observation');
        },

        async saveObservation() {
            if (!this.form.body.trim()) return;
            await this.engine.enqueue('observation', 'create', { uuid: uuid(), horse_id: this.horse.id, body: this.form.body, severity: this.form.severity, observed_at: new Date().toISOString() });
            await this.load();
            this.notify('Observation enregistrée');
            this.go('horse');
            if (this.online) this.syncNow();
        },

        startHorseNotes() {
            this.form = { particularities: this.horse.particularities ?? '', general_notes: this.horse.general_notes ?? '', current_location: this.horse.current_location ?? '' };
            this.go('horse-notes');
        },

        async saveHorseNotes() {
            const h = clone(this.horse);
            const changes = {};
            const base = {};
            for (const k of Object.keys(this.form)) {
                const v = this.form[k] === '' ? null : this.form[k];
                if ((h[k] ?? null) !== v) { changes[k] = v; base[k] = h[k] ?? null; h[k] = v; }
            }
            if (Object.keys(changes).length) {
                await this.engine.enqueue('horse', 'update', { horse_id: h.id, changes, base }, ['horses'], (tx) => tx.objectStore('horses').put(h));
                this.horse = h;
                await this.load();
                if (this.online) this.syncNow();
            }
            this.go('horse');
        },

        // ---------------------------------------------------------- Conflits / rejets
        async loadConflicts() {
            if (!this.online) { this.notify('Connexion requise pour consulter les conflits'); return; }
            try {
                this.conflicts = (await this.api.conflicts()).conflicts;
                this.conflictsLoaded = true;
            } catch { this.notify('Impossible de charger les conflits'); }
        },

        async resolveConflict(c, choice) {
            try {
                await this.api.resolve(c.id, choice);
                this.conflicts = this.conflicts.filter((x) => x.id !== c.id);
                await this.db.put('meta', this.conflicts.length, 'open_conflicts');
                this.engine.state.conflicts = this.conflicts.length;
                await this.syncNow();
            } catch (e) { this.notify(e.message || 'Résolution impossible'); }
        },

        async dismissRejected(op) {
            if (!confirm('Supprimer définitivement cette saisie refusée de l\'appareil ?')) return;
            await this.engine.dismissRejected(op.op_uuid);
            this.rejected = await this.db.getAll('rejected');
        },

        describeOp(op) {
            const labels = { 'session.create': 'Nouvelle séance', 'session.update': 'Modification de séance', 'session.complete': 'Bilan de séance', 'session_exercise.create': 'Ajout d\'exercice', 'session_exercise.update': 'Exercice réalisé / modifié', 'comment.create': 'Commentaire', 'daily_log.create': 'Suivi quotidien', 'observation.create': 'Observation', 'horse.update': 'Notes du cheval' };
            return labels[`${op.entity}.${op.action}`] ?? `${op.entity}.${op.action}`;
        },

        // ---------------------------------------------------------- Code PIN
        async setPin() {
            const pin = prompt('Choisissez un code PIN (4 à 8 chiffres) pour verrouiller l\'application après 15 minutes d\'inactivité :');
            if (pin === null) return;
            if (!/^\d{4,8}$/.test(pin)) { this.notify('Code invalide'); return; }
            await this.db.put('meta', await hashPin(pin), 'pin');
            this.pinSet = true;
            this.notify('Code PIN activé');
        },
        async removePin() {
            await this.db.delete('meta', 'pin');
            this.pinSet = false;
            this.notify('Code PIN désactivé');
        },
        async unlock() {
            if (await verifyPin(this.pinInput, await this.db.get('meta', 'pin'))) {
                this.locked = false; this.pinInput = ''; this.pinError = null; this.lastActivity = Date.now();
            } else {
                this.pinError = 'Code incorrect';
                this.pinInput = '';
            }
        },
        async forgetDevice() {
            if (!confirm('Effacer toutes les données locales de cet appareil (y compris les saisies non synchronisées) ?')) return;
            this.db.close();
            await purgeAll();
            location.href = '/dashboard';
        },
    };
}
