// Jack&Cie — site vitrine : menu mobile, en-tête au défilement, bascule mensuel/annuel, apparitions douces.
document.documentElement.classList.remove('no-js');

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
