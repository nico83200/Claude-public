/**
 * Administration › Simulation : création des comptes de test par petites étapes (pas de requête longue
 * chez l'hébergeur), suppression de même, et état du serveur relevé en continu.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function post(url, body = null) {
    const res = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: body ? JSON.stringify(body) : null,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || `Erreur ${res.status}`);
    return data;
}

const fill = (tpl, id) => tpl.replace('__ID__', id);

export function loadSimulation({ storeUrl, stepUrl, stopUrl, resumeUrl, purgeUrl, active = null, batches = [] }) {
    return {
        form: {},
        run: active, // avancement du lot en cours
        running: false,
        purging: null, // id du lot en cours de suppression
        purgeLeft: null,
        error: null,
        steps: [], // durées des dernières étapes (ms par compte)
        batches,
        init() {
            if (this.run && this.run.status === 'purging') this.purge(this.run.id, false);
        },
        estimate() {
            const f = this.form, n = +f.accounts || 0, h = +f.horses || 0;
            return n * h * ((+f.events || 0) + (+f.sessions || 0) * 2 + (+f.cares || 0) + (+f.expenses || 0) + (+f.logs || 0)) + n * 4;
        },
        avgStep() {
            if (!this.steps.length) return null;
            return Math.round(this.steps.reduce((a, b) => a + b, 0) / this.steps.length);
        },
        async start() {
            this.error = null;
            try {
                this.run = await post(storeUrl, this.form);
                this.refreshBatch(this.run);
                this.steps = [];
                this.loop();
            } catch (e) { this.error = e.message; }
        },
        async loop() {
            if (this.running || !this.run) return;
            this.running = true;
            try {
                while (this.run.status === 'running') {
                    const data = await post(fill(stepUrl, this.run.id));
                    if (data.step_accounts) this.steps = [...this.steps.slice(-19), data.step_ms / data.step_accounts];
                    this.run = data;
                    this.refreshBatch(data);
                }
            } catch (e) {
                this.error = e.message + ' — vous pouvez reprendre la simulation.';
                await post(fill(stopUrl, this.run.id)).then((d) => { this.run = d; }).catch(() => {});
            } finally {
                this.running = false;
                this.refreshBatch(this.run);
            }
        },
        async stop() {
            if (!this.run) return;
            this.run = await post(fill(stopUrl, this.run.id));
        },
        async resume(id) {
            this.error = null;
            try {
                this.run = await post(fill(resumeUrl, id));
                this.loop();
            } catch (e) { this.error = e.message; }
        },
        async purge(id, ask = true) {
            if (this.purging) return;
            if (ask && !window.confirm('Supprimer définitivement tous les comptes de test de cette simulation et tout ce qu\'ils ont créé ?')) return;
            this.purging = id;
            this.error = null;
            try {
                let data;
                do {
                    data = await post(fill(purgeUrl, id));
                    this.purgeLeft = data.left;
                } while (data.left > 0);
                this.refreshBatch({ id, status: 'purged', label: 'Supprimée' });
            } catch (e) { this.error = e.message; } finally { this.purging = null; this.purgeLeft = null; }
        },
        async purgeAll() {
            const ids = this.batches.filter((b) => b.status !== 'purged').map((b) => b.id);
            if (!ids.length || !window.confirm(`Supprimer définitivement les données de ${ids.length} simulation(s) ?`)) return;
            if (this.run?.status === 'running') await this.stop();
            for (const id of ids) await this.purge(id, false);
        },
        refreshBatch(p) {
            if (!p) return;
            const b = this.batches.find((x) => x.id === p.id);
            if (b) Object.assign(b, { status: p.status, label: p.label, done: p.done ?? b.done, counts: p.counts ?? b.counts });
            else this.batches.unshift({ id: p.id, status: p.status, label: p.label, done: p.done, total: p.total, counts: p.counts, date: 'à l\'instant' });
        },
    };
}

/** État du serveur : relevé toutes les quelques secondes, courbes des dernières minutes. */
export function serverMonitor({ url, every = 3 }) {
    return {
        snap: null,
        every,
        paused: false,
        error: null,
        series: { response: [], load: [], qps: [] },
        _last: null,
        _timer: null,
        init() {
            this.tick();
            this.$watch('every', () => this.schedule());
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.tick(); });
        },
        schedule() {
            clearTimeout(this._timer);
            if (!this.paused) this._timer = setTimeout(() => this.tick(), this.every * 1000);
        },
        toggle() { this.paused = !this.paused; this.paused ? clearTimeout(this._timer) : this.tick(); },
        push(key, v) { if (v !== null && v !== undefined) this.series[key] = [...this.series[key].slice(-59), v]; },
        async tick() {
            if (document.hidden) return this.schedule();
            const t0 = performance.now();
            try {
                const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!res.ok) throw new Error(`Erreur ${res.status}`);
                const snap = await res.json();
                snap.response_ms = Math.round(performance.now() - t0);
                const q = snap.database?.status?.Questions;
                if (q !== undefined && this._last) {
                    const dt = (Date.parse(snap.at) - Date.parse(this._last.at)) / 1000;
                    snap.qps = dt > 0 ? Math.max(0, Math.round((q - this._last.q) / dt)) : null;
                }
                this._last = { at: snap.at, q };
                this.push('response', snap.response_ms);
                this.push('load', snap.system?.load?.[0] ?? null);
                this.push('qps', snap.qps ?? null);
                this.snap = snap;
                this.error = null;
            } catch (e) {
                this.error = navigator.onLine ? e.message : 'Hors ligne';
            }
            this.schedule();
        },
        /** Tracé SVG (0..100 en x, 0..30 en y) d'une série. */
        path(key) {
            const s = this.series[key];
            if (s.length < 2) return '';
            const max = Math.max(...s, 1);
            return s.map((v, i) => `${i ? 'L' : 'M'}${(i / (s.length - 1) * 100).toFixed(1)},${(30 - v / max * 28).toFixed(1)}`).join(' ');
        },
        pct(a, b) { return a !== null && b ? Math.round(a / b * 100) : null; },
    };
}

/**
 * Simulation de navigation (rendu interne) : le serveur génère de vraies pages comme des comptes de test ;
 * plusieurs étapes en parallèle occupent plusieurs processus PHP, comme des visiteurs simultanés.
 */
export function navigationSimulation({ url }) {
    return {
        opts: { parallel: 2, seconds: 60, pages: 10 },
        running: false,
        stopAsked: false,
        error: null,
        elapsed: 0,
        total: { n: 0, errors: 0, ms: 0, queries: 0 },
        pages: {}, // libellé → { n, errors, ms, max, queries, samples[] }
        recent: [],
        async start() {
            if (this.running) return;
            Object.assign(this, { running: true, stopAsked: false, error: null, elapsed: 0, pages: {}, recent: [], total: { n: 0, errors: 0, ms: 0, queries: 0 } });
            const t0 = performance.now();
            const end = t0 + this.opts.seconds * 1000;
            const timer = setInterval(() => { this.elapsed = Math.round((performance.now() - t0) / 1000); }, 500);
            const worker = async () => {
                while (!this.stopAsked && performance.now() < end) {
                    try {
                        const data = await post(url, { pages: this.opts.pages });
                        data.visits.forEach((v) => this.record(v));
                    } catch (e) { this.error = e.message; this.stopAsked = true; }
                }
            };
            await Promise.all(Array.from({ length: Math.max(1, Math.min(6, +this.opts.parallel || 1)) }, worker));
            clearInterval(timer);
            this.elapsed = Math.max(1, Math.round((performance.now() - t0) / 1000));
            this.running = false;
        },
        stop() { this.stopAsked = true; },
        record(v) {
            const p = this.pages[v.label] ??= { n: 0, errors: 0, ms: 0, max: 0, queries: 0, samples: [] };
            const bad = v.status >= 400;
            p.n++; p.ms += v.ms; p.max = Math.max(p.max, v.ms); p.queries += v.queries; p.samples = [...p.samples.slice(-199), v.ms];
            if (bad) p.errors++;
            this.total.n++; this.total.ms += v.ms; this.total.queries += v.queries;
            if (bad) this.total.errors++;
            this.recent = [...this.recent.slice(-119), v.ms];
        },
        p95(s) {
            if (!s?.length) return null;
            const a = [...s].sort((x, y) => x - y);
            return Math.round(a[Math.min(a.length - 1, Math.floor(a.length * 0.95))]);
        },
        rate() { return this.elapsed ? (this.total.n / this.elapsed).toFixed(1) : '0'; },
        avg() { return this.total.n ? Math.round(this.total.ms / this.total.n) : 0; },
        rows() {
            return Object.entries(this.pages).map(([label, p]) => ({ label, n: p.n, errors: p.errors, avg: Math.round(p.ms / p.n), p95: this.p95(p.samples), max: Math.round(p.max), queries: Math.round(p.queries / p.n) }))
                .sort((a, b) => b.avg - a.avg);
        },
        path() {
            const s = this.recent;
            if (s.length < 2) return '';
            const max = Math.max(...s, 1);
            return s.map((v, i) => `${i ? 'L' : 'M'}${(i / (s.length - 1) * 100).toFixed(1)},${(30 - v / max * 28).toFixed(1)}`).join(' ');
        },
    };
}
