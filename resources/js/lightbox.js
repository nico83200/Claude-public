/**
 * Visionneuse d'images : tout lien <a data-lightbox="groupe" href="image"> s'ouvre dans une fenêtre
 * plein écran au lieu d'un nouvel onglet. Images du même groupe : flèches, glisser du doigt, clavier.
 * Le bouton « retour » du téléphone ferme la visionneuse. Sans JavaScript, le lien reste un lien.
 */
import { renderPdf } from './pdf-view.js';

let state = null;

const icon = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="${d}" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

function render() {
    const { items, index, root } = state;
    const item = items[index];
    const img = root.querySelector('.jc-lb-img');
    const pdf = root.querySelector('.jc-lb-pdf');
    const fail = root.querySelector('.jc-lb-fail');
    state.stopPdf?.();
    state.stopPdf = null;
    fail.hidden = true;
    img.classList.remove('is-loaded');
    root.classList.toggle('is-pdf', item.type === 'pdf');
    if (item.type === 'pdf') {
        img.hidden = true;
        img.removeAttribute('src');
        pdf.hidden = false;
        state.stopPdf = renderPdf(item.src, pdf, () => { pdf.hidden = true; fail.hidden = false; });
    } else {
        pdf.hidden = true;
        pdf.innerHTML = '';
        img.hidden = false;
        img.alt = item.caption || '';
        img.src = item.src;
    }
    fail.querySelector('a').href = item.download || item.src;
    root.querySelector('.jc-lb-caption').textContent = item.caption || '';
    root.querySelector('.jc-lb-count').textContent = items.length > 1 ? `${index + 1} / ${items.length}` : '';
    root.querySelector('.jc-lb-download').href = item.download || item.src;
    root.querySelectorAll('.jc-lb-nav').forEach((b) => { b.hidden = items.length < 2; });
    // Images voisines chargées d'avance.
    [index - 1, index + 1].forEach((i) => { if (items[i] && items[i].type !== 'pdf') new Image().src = items[i].src; });
}

function go(step) {
    if (!state || state.items.length < 2) return;
    state.index = (state.index + step + state.items.length) % state.items.length;
    render();
}

function close(fromHistory = false) {
    if (!state) return;
    const { root, opener, onKey } = state;
    state.stopPdf?.();
    document.removeEventListener('keydown', onKey, true);
    root.classList.remove('is-visible');
    document.documentElement.style.overflow = '';
    setTimeout(() => root.remove(), 180);
    state = null;
    if (!fromHistory && history.state?.jcLightbox) history.back();
    opener?.focus?.();
}

function open(link) {
    const group = link.dataset.lightbox;
    const links = group ? [...document.querySelectorAll(`a[data-lightbox="${CSS.escape(group)}"]`)] : [link];
    const items = links.map((a) => ({
        src: a.dataset.lightboxSrc || a.href,
        type: a.dataset.lightboxType || 'image',
        download: a.dataset.lightboxDownload || a.href,
        caption: a.dataset.caption || a.querySelector('img')?.alt || a.getAttribute('title') || '',
    }));

    const root = document.createElement('div');
    root.className = 'jc-lb';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', link.dataset.lightboxType === 'pdf' ? 'Document' : 'Photo');
    root.innerHTML = `
        <div class="jc-lb-bar">
            <span class="jc-lb-count"></span>
            <span class="jc-lb-actions">
                <a class="jc-lb-btn jc-lb-download" download aria-label="Télécharger">${icon('M12 4v11m0 0 4.5-4.5M12 15l-4.5-4.5M5 19h14')}</a>
                <button type="button" class="jc-lb-btn jc-lb-close" aria-label="Fermer">${icon('M6 6l12 12M18 6 6 18')}</button>
            </span>
        </div>
        <button type="button" class="jc-lb-nav is-prev" aria-label="Photo précédente">${icon('M15 5l-7 7 7 7')}</button>
        <figure class="jc-lb-stage"><img class="jc-lb-img" alt=""><div class="jc-lb-pdf" hidden></div>
            <p class="jc-lb-fail" hidden>Aperçu impossible pour ce fichier. <a download>Le télécharger</a></p>
            <figcaption class="jc-lb-caption"></figcaption></figure>
        <button type="button" class="jc-lb-nav is-next" aria-label="Photo suivante">${icon('M9 5l7 7-7 7')}</button>`;
    (link.closest('dialog') || document.body).appendChild(root);

    const onKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); close(); }
        else if (e.key === 'ArrowLeft') go(-1);
        else if (e.key === 'ArrowRight') go(1);
    };
    state = { items, index: Math.max(0, links.indexOf(link)), root, opener: link, onKey };

    const img = root.querySelector('.jc-lb-img');
    img.addEventListener('load', () => img.classList.add('is-loaded'));
    // Image illisible par le navigateur (HEIC hors iPhone…) : proposition de téléchargement.
    img.addEventListener('error', () => { if (img.getAttribute('src')) { img.hidden = true; root.querySelector('.jc-lb-fail').hidden = false; } });
    root.querySelector('.jc-lb-close').addEventListener('click', () => close());
    root.querySelector('.is-prev').addEventListener('click', () => go(-1));
    root.querySelector('.is-next').addEventListener('click', () => go(1));
    // Toucher le fond (hors image et boutons) ferme.
    root.addEventListener('click', (e) => { if (e.target === root || e.target.classList.contains('jc-lb-stage')) close(); });
    // Glisser : gauche / droite pour changer de photo, vers le bas pour fermer.
    let start = null;
    root.addEventListener('touchstart', (e) => { if (e.touches.length === 1) start = { x: e.touches[0].clientX, y: e.touches[0].clientY }; }, { passive: true });
    root.addEventListener('touchend', (e) => {
        if (!start || e.changedTouches.length !== 1) return;
        const dx = e.changedTouches[0].clientX - start.x, dy = e.changedTouches[0].clientY - start.y;
        start = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) go(dx < 0 ? 1 : -1);
        else if (dy > 90 && Math.abs(dy) > Math.abs(dx) && !root.classList.contains('is-pdf')) close();
    }, { passive: true });
    document.addEventListener('keydown', onKey, true);

    document.documentElement.style.overflow = 'hidden';
    history.pushState({ ...(history.state || {}), jcLightbox: true }, '');
    render();
    requestAnimationFrame(() => root.classList.add('is-visible'));
    root.querySelector('.jc-lb-close').focus();
}

export function startLightbox() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('a[data-lightbox]');
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        open(link);
    });
    window.addEventListener('popstate', () => { if (state) close(true); });
}
