/**
 * Statuts « Prévu / En cours / À valider » des séances et rendez-vous : tenus à jour sur une page restée ouverte
 * (même règle que App\Support\TimeStatus côté serveur).
 */
function stateOf(d, now) {
    if (d.live) return now < d.end + d.grace ? 'ongoing' : 'to_validate';
    if (now < d.start) return d.confirmed ? 'confirmed' : 'planned';
    return now < d.end ? 'ongoing' : 'to_validate';
}

function refresh() {
    const now = Date.now();
    document.querySelectorAll('[data-time-status]').forEach((el) => {
        let d;
        try { d = JSON.parse(el.dataset.timeStatus); } catch { return; }
        const key = stateOf(d, now);
        if (el.dataset.status === key) return;
        const [label, cls] = d.states[key];
        const [, oldCls] = d.states[el.dataset.status] || [];
        if (oldCls) el.classList.remove(...oldCls.split(' '));
        el.classList.add(...cls.split(' '));
        el.textContent = label;
        el.dataset.status = key;
    });
}

export function startTimeStatus() {
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', refresh);
}
