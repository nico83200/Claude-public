/**
 * Courbe de poids d'un cheval (une seule série : pas de légende, le titre la nomme).
 * Ligne 2 px, points de 8 px cerclés de la couleur du fond, voile à 10 %, grille fine,
 * dernière valeur étiquetée, réticule et bulle au survol / toucher. Les valeurs restent lisibles
 * dans le tableau des mesures sous la courbe.
 */
const NS = 'http://www.w3.org/2000/svg';
const node = (tag, attrs = {}, parent) => {
    const n = document.createElementNS(NS, tag);
    Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v));
    parent?.appendChild(n);
    return n;
};
const fmt = (v) => v.toLocaleString('fr-FR', { maximumFractionDigits: 1 });

function niceTicks(min, max, count = 4) {
    const span = Math.max(max - min, 10);
    const raw = span / count;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) || raw;
    const lo = Math.floor(min / step) * step;
    const hi = Math.ceil(max / step) * step;
    const ticks = [];
    for (let v = lo; v <= hi + step / 2; v += step) ticks.push(Math.round(v * 10) / 10);
    return ticks;
}

export function weightChart(points) {
    return {
        points,
        tip: null,
        init() {
            this.draw();
            new ResizeObserver(() => this.draw()).observe(this.$refs.box);
        },
        draw() {
            const box = this.$refs.box;
            const svg = this.$refs.svg;
            svg.innerHTML = '';
            const W = box.clientWidth || 320;
            const H = 220;
            const pad = { l: 44, r: 56, t: 16, b: 28 };
            svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
            svg.setAttribute('width', W);
            svg.setAttribute('height', H);
            const pts = this.points;
            if (!pts.length) return;

            const times = pts.map((p) => new Date(p.d).getTime());
            const t0 = Math.min(...times), t1 = Math.max(...times);
            const ws = pts.map((p) => p.w);
            const ticks = niceTicks(Math.min(...ws) - 5, Math.max(...ws) + 5);
            const y0 = ticks[0], y1 = ticks.at(-1);
            const x = (t) => (t1 === t0 ? (pad.l + W - pad.r) / 2 : pad.l + (t - t0) / (t1 - t0) * (W - pad.l - pad.r));
            const y = (v) => pad.t + (1 - (v - y0) / (y1 - y0)) * (H - pad.t - pad.b);

            // Grille et axe des poids (fins, discrets).
            ticks.forEach((v) => {
                node('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v), class: 'wc-grid' }, svg);
                node('text', { x: pad.l - 8, y: y(v) + 4, 'text-anchor': 'end', class: 'wc-axis' }, svg).textContent = fmt(v);
            });
            // Dates : première et dernière.
            const first = node('text', { x: x(t0), y: H - 8, 'text-anchor': pts.length > 1 ? 'start' : 'middle', class: 'wc-axis' }, svg);
            first.textContent = pts[0].label;
            if (pts.length > 1) node('text', { x: x(t1), y: H - 8, 'text-anchor': 'end', class: 'wc-axis' }, svg).textContent = pts.at(-1).label;

            const xy = pts.map((p, i) => [x(times[i]), y(p.w)]);
            if (xy.length > 1) {
                const line = xy.map(([a, b], i) => `${i ? 'L' : 'M'}${a.toFixed(1)},${b.toFixed(1)}`).join('');
                node('path', { d: `${line}L${xy.at(-1)[0]},${y(y0)}L${xy[0][0]},${y(y0)}Z`, class: 'wc-area' }, svg);
                node('path', { d: line, class: 'wc-line' }, svg);
            }
            xy.forEach(([a, b], i) => node('circle', { cx: a, cy: b, r: 4, class: 'wc-dot' + (pts[i].m !== 'scale' ? ' is-estimate' : '') }, svg));
            // Étiquette directe : la dernière valeur seulement.
            const [lx, ly] = xy.at(-1);
            node('text', { x: lx + 10, y: ly + 4, class: 'wc-label' }, svg).textContent = `${fmt(pts.at(-1).w)} kg`;

            this.geo = { xy, pad, W, H };
            this.cross = node('line', { y1: pad.t, y2: H - pad.b, class: 'wc-cross', visibility: 'hidden' }, svg);
        },
        move(e) {
            if (!this.geo) return;
            const r = this.$refs.svg.getBoundingClientRect();
            const px = (e.touches?.[0]?.clientX ?? e.clientX) - r.left;
            let best = 0;
            this.geo.xy.forEach(([a], i) => { if (Math.abs(a - px) < Math.abs(this.geo.xy[best][0] - px)) best = i; });
            const [a, b] = this.geo.xy[best];
            this.cross.setAttribute('x1', a); this.cross.setAttribute('x2', a); this.cross.setAttribute('visibility', 'visible');
            const p = this.points[best];
            this.tip = { left: Math.min(Math.max(a, 70), this.geo.W - 70), top: b, title: p.label, value: `${fmt(p.w)} kg`, method: p.m === 'scale' ? 'Pesée' : 'Estimation' };
        },
        leave() {
            this.tip = null;
            this.cross?.setAttribute('visibility', 'hidden');
        },
    };
}
