// Jack&Cie — site vitrine : menu mobile, en-tête au défilement, bascule mensuel/annuel, apparitions douces.
document.documentElement.classList.remove('no-js');

/*
 * Code promo et source dans l'adresse du site (ex. jackcie.fr/?code=PRINTEMPS&src=flyer) :
 * retenus 30 jours sur l'appareil, puis ajoutés à chaque lien d'inscription vers l'application.
 * L'application applique le code à la création du compte (y compris dans l'application installée)
 * et compte l'inscription dans la source (Administration › Codes › Sources).
 */
const attribution = (() => {
    const KEY = 'jc-attribution';
    const q = new URLSearchParams(location.search);
    const code = (q.get('code') || q.get('promo') || '').toUpperCase().replace(/[^A-Z0-9_-]/g, '').slice(0, 40);
    const src = (q.get('src') || q.get('source') || q.get('utm_source') || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    let data = null;
    try { data = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch { /* stockage indisponible */ }
    if (data && Date.now() - (data.at || 0) > 30 * 864e5) data = null;
    if (code || src) {
        // Ce qui est dans l'adresse remplace l'ancienne valeur ; ce qui n'y est pas garde la valeur déjà retenue.
        data = { code: code || data?.code || '', src: src || data?.src || '', at: Date.now() };
        try { localStorage.setItem(KEY, JSON.stringify(data)); } catch { /* ignoré */ }
    }
    if (!data || (!data.code && !data.src)) return null;
    document.querySelectorAll('a[href*="/register"]').forEach((a) => {
        const u = new URL(a.href, location.href);
        if (u.host === location.host) return;
        if (data.code) u.searchParams.set('code', data.code);
        if (data.src) u.searchParams.set('src', data.src);
        a.href = u.toString();
    });
    // Code personnel : bandeau dédié à la place de l'offre générale du site.
    if (data.code) {
        const bar = document.getElementById('code-bar');
        const promo = document.getElementById('promo');
        const offer = promo?.dataset.promo || document.getElementById('promo-bar')?.dataset.promo;
        if (data.code !== offer) {
            if (bar) { bar.querySelector('[data-code-value]').textContent = data.code; bar.hidden = false; }
            document.getElementById('promo-bar')?.remove();
            promo?.remove();
        }
    }
    return data;
})();

const header = document.querySelector('.header');
const onScroll = () => header?.classList.toggle('scrolled', window.scrollY > 8);
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

const menuBtn = document.querySelector('.menu-btn');
const nav = document.getElementById('nav');
menuBtn?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', String(open));
    menuBtn.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
});
nav?.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => {
    nav.classList.remove('open');
    menuBtn?.setAttribute('aria-expanded', 'false');
}));
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav?.classList.contains('open')) { nav.classList.remove('open'); menuBtn?.setAttribute('aria-expanded', 'false'); menuBtn?.focus(); }
});

// Tarifs : mensuel / annuel
document.querySelectorAll('[data-billing-toggle] button').forEach((btn) => {
    btn.addEventListener('click', () => {
        const scope = document.querySelector('[data-billing]');
        scope?.setAttribute('data-billing', btn.dataset.value);
        btn.parentElement.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
    });
});

// Apparition douce des blocs
const items = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    }), { rootMargin: '0px 0px -8% 0px' });
    items.forEach((el) => io.observe(el));
} else {
    items.forEach((el) => el.classList.add('in'));
}

// Formulaire de contact : retour d'envoi (?envoye=1 / ?erreur=…)
const status = new URLSearchParams(location.search);
const box = document.querySelector('[data-form-status]');
if (box && (status.has('envoye') || status.has('erreur'))) {
    box.hidden = false;
    box.className = 'alert ' + (status.has('envoye') ? 'ok' : 'err');
    box.textContent = status.has('envoye')
        ? 'Merci, votre message est bien parti ! Nous vous répondons par e-mail, en général sous 48 h ouvrées.'
        : ({ champs: 'Merci de renseigner votre nom, une adresse e-mail valide et votre message.', envoi: 'Le message n\'a pas pu partir. Écrivez-nous directement à contact@jackcie.fr.' }[status.get('erreur')] || 'Le message n\'a pas pu partir.');
    box.scrollIntoView({ block: 'center' });
}

// Bandeau de l'offre : visible sur toutes les pages jusqu'à la date de fin.
(() => {
    const bar = document.getElementById('promo-bar');
    if (bar && Date.now() <= new Date(bar.dataset.until + 'T23:59:59').getTime()) bar.hidden = false;
})();

// Fenêtre promotionnelle : une fois par visiteur (fermeture retenue 7 jours), jusqu'à la date de fin de l'offre.
(() => {
    const box = document.getElementById('promo');
    if (!box) return;
    const key = 'jc-promo-' + box.dataset.promo;
    const until = new Date(box.dataset.until + 'T23:59:59');
    let seen = 0;
    try { seen = Number(localStorage.getItem(key) || 0); } catch { /* stockage indisponible */ }
    if (Date.now() > until.getTime() || Date.now() - seen < 7 * 864e5 || new URLSearchParams(location.search).has('sans-promo')) return;
    let opener = null;
    const remember = () => { try { localStorage.setItem(key, String(Date.now())); } catch { /* ignoré */ } };
    const close = () => {
        box.classList.remove('open');
        remember();
        document.documentElement.style.overflow = '';
        document.removeEventListener('keydown', onKey);
        setTimeout(() => { box.hidden = true; }, 300);
        opener?.focus?.();
    };
    const onKey = (e) => {
        if (e.key === 'Escape') close();
        if (e.key === 'Tab') { // la tabulation reste dans la fenêtre
            const f = [...box.querySelectorAll('a[href], button')];
            const first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    };
    const open = () => {
        if (!box.hidden || document.querySelector('.nav.open')) return;
        opener = document.activeElement;
        box.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => box.classList.add('open')));
        box.querySelector('[data-promo-cta]').focus({ preventScroll: true });
        document.addEventListener('keydown', onKey);
        window.removeEventListener('scroll', onScroll);
    };
    const onScroll = () => { if (window.scrollY > window.innerHeight * 0.6) open(); };
    box.querySelectorAll('[data-promo-close]').forEach((el) => el.addEventListener('click', close));
    box.querySelector('[data-promo-cta]').addEventListener('click', remember);
    // Après quelques secondes de lecture, ou plus tôt si le visiteur fait défiler la page.
    setTimeout(open, 6000);
    window.addEventListener('scroll', onScroll, { passive: true });
})();
