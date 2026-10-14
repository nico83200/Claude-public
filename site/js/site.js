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
