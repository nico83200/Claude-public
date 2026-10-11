/**
 * Menus déroulants modernes.
 *
 * Chaque <select class="input"> (hors sélection multiple et data-native) reçoit un bouton et une liste
 * à l'image de l'application : fenêtre ancrée sur ordinateur, panneau venant du bas sur téléphone,
 * recherche au-delà de 10 choix, clavier (flèches, Entrée, Échap, saisie de la première lettre).
 * Le <select> d'origine reste dans la page (invisible) : c'est lui qui est envoyé avec le formulaire,
 * qui porte la validation « obligatoire » et qui émet « change » (filtres, x-model, onchange).
 */
const ENHANCED = new WeakSet();
const isPhone = () => window.matchMedia('(max-width: 639px)').matches;
let open = null; // menu ouvert : { close() }

const el = (tag, cls, attrs = {}) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v));
    return n;
};
const chevron = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5.5 7.75 4.5 4.5 4.5-4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
const check = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4.5 10.5 3.5 3.5 7.5-8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function labelFor(select) {
    if (select._jcLabel) return select._jcLabel;
    const l = select.id && document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
    return (l?.textContent || select.getAttribute('aria-label') || '').replace('*', '').trim();
}

function enhance(select) {
    if (ENHANCED.has(select) || select.multiple || select.dataset.native !== undefined || select.size > 1) return;
    ENHANCED.add(select);

    const wrap = el('div', 'jc-select');
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select._jcLabel = labelFor(select);
    select.classList.add('jc-select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const button = el('button', select.className.replace('jc-select-native', '').trim() + ' jc-select-button', {
        type: 'button', 'aria-haspopup': 'listbox', 'aria-expanded': 'false',
    });
    if (select.id) {
        // Le libellé du formulaire désigne désormais le bouton.
        button.id = select.id + '__btn';
        document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((l) => l.setAttribute('for', button.id));
    }
    const text = el('span', 'jc-select-text');
    const icon = el('span', 'jc-select-chevron');
    icon.innerHTML = chevron;
    button.append(text, icon);
    wrap.appendChild(button);

    let last = null;
    const sync = () => {
        const opt = select.options[select.selectedIndex];
        const key = select.value + '|' + (opt?.text ?? '') + '|' + select.disabled;
        if (key === last) return;
        last = key;
        const placeholder = !opt || opt.value === '';
        text.textContent = opt?.text || '—';
        text.classList.toggle('is-placeholder', placeholder);
        button.disabled = select.disabled;
    };
    sync();
    select.addEventListener('change', sync);
    select._jcSync = sync;
    // Message de validation du navigateur : on remet la main sur le bouton.
    select.addEventListener('invalid', () => setTimeout(() => button.focus()));

    button.addEventListener('click', () => (open?.select === select ? open.close() : show(select, button, wrap)));
    button.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
            e.preventDefault();
            show(select, button, wrap);
        } else if (e.key.length === 1 && /\S/.test(e.key)) {
            // Saisie de la première lettre, menu fermé : choisit directement.
            const opts = [...select.options].filter((o) => !o.disabled && o.value !== '');
            const start = opts.indexOf(select.options[select.selectedIndex]) + 1;
            const hit = [...opts.slice(start), ...opts.slice(0, start)].find((o) => norm(o.text).startsWith(norm(e.key)));
            if (hit) choose(select, hit.value);
        }
    });
}

function choose(select, value) {
    if (select.value !== value) {
        select.value = value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    select._jcSync?.();
}

function show(select, button, wrap) {
    open?.close();
    const phone = isPhone();
    const host = select.closest('dialog') || document.body;
    const layer = el('div', 'jc-select-layer' + (phone ? ' is-sheet' : ''));
    const panel = el('div', 'jc-select-panel', { role: 'dialog', 'aria-label': labelFor(select) || 'Choisir' });
    if (phone) {
        layer.appendChild(el('div', 'jc-select-backdrop'));
        const head = el('div', 'jc-select-head');
        head.innerHTML = '<span class="jc-select-grip"></span>';
        const title = el('p', 'jc-select-title');
        title.textContent = labelFor(select) || 'Choisir';
        head.appendChild(title);
        panel.appendChild(head);
    }
    const options = [...select.querySelectorAll('option')];
    let search = null;
    if (options.length > 10) {
        search = el('input', 'jc-select-search', { type: 'search', placeholder: 'Rechercher…', 'aria-label': 'Rechercher', autocomplete: 'off', enterkeyhint: 'search' });
        panel.appendChild(search);
    }
    const list = el('ul', 'jc-select-list', { role: 'listbox' });
    panel.appendChild(list);
    layer.appendChild(panel);
    host.appendChild(layer);

    const items = [];
    let group = null;
    options.forEach((o) => {
        const og = o.parentElement.tagName === 'OPTGROUP' ? o.parentElement : null;
        if (og && og !== group) {
            group = og;
            const g = el('li', 'jc-select-group', { role: 'presentation' });
            g.textContent = og.label;
            list.appendChild(g);
        }
        const li = el('li', 'jc-select-option', { role: 'option', 'aria-selected': String(o.selected && o.value !== '') });
        if (o.disabled) li.setAttribute('aria-disabled', 'true');
        if (o.value === '') li.classList.add('is-placeholder');
        li.innerHTML = `<span class="jc-select-check">${check}</span>`;
        const t = el('span', 'jc-select-option-text');
        t.textContent = o.text;
        li.prepend(t);
        li.addEventListener('mousedown', (e) => e.preventDefault());
        li.addEventListener('click', () => {
            if (o.disabled) return;
            choose(select, o.value);
            close();
        });
        list.appendChild(li);
        items.push({ li, o, text: norm(o.text) });
    });

    let active = Math.max(0, items.findIndex((i) => i.o.selected));
    const visible = () => items.filter((i) => !i.li.hidden && !i.o.disabled);
    const highlight = (idx) => {
        const v = visible();
        if (!v.length) return;
        active = (idx + v.length) % v.length;
        items.forEach((i) => i.li.classList.remove('is-active'));
        v[active].li.classList.add('is-active');
        v[active].li.scrollIntoView({ block: 'nearest' });
    };

    const place = () => {
        if (phone) return;
        const r = button.getBoundingClientRect();
        const below = window.innerHeight - r.bottom - 12;
        const above = r.top - 12;
        const up = below < 240 && above > below;
        panel.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - Math.max(r.width, 220) - 8))}px`;
        panel.style.minWidth = `${r.width}px`;
        panel.style.maxWidth = `${Math.max(r.width, Math.min(420, window.innerWidth - 16))}px`;
        panel.style.maxHeight = `${Math.min(360, up ? above : below)}px`;
        panel.style.top = up ? '' : `${r.bottom + 6}px`;
        panel.style.bottom = up ? `${window.innerHeight - r.top + 6}px` : '';
    };
    place();

    const onKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); close(); button.focus(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const v = visible()[active];
            if (v) { choose(select, v.o.value); close(); button.focus(); }
        } else if (e.key === 'Tab') close();
    };
    const onOutside = (e) => { if (!panel.contains(e.target) && !button.contains(e.target)) close(); };
    const onScroll = (e) => { if (!panel.contains(e.target)) place(); };

    search?.addEventListener('input', () => {
        const q = norm(search.value.trim());
        items.forEach((i) => { i.li.hidden = q !== '' && !i.text.includes(q); });
        list.querySelectorAll('.jc-select-group').forEach((g) => {
            let n = g.nextElementSibling, any = false;
            while (n && !n.classList.contains('jc-select-group')) { any ||= !n.hidden; n = n.nextElementSibling; }
            g.hidden = !any;
        });
        highlight(0);
    });
    layer.querySelector('.jc-select-backdrop')?.addEventListener('click', () => close());
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('pointerdown', onOutside, true);
    window.addEventListener('resize', place);
    window.addEventListener('scroll', onScroll, true);

    button.setAttribute('aria-expanded', 'true');
    wrap.classList.add('is-open');
    requestAnimationFrame(() => layer.classList.add('is-visible'));
    highlight(active);
    if (search && !phone) search.focus();

    function close() {
        document.removeEventListener('keydown', onKey, true);
        document.removeEventListener('pointerdown', onOutside, true);
        window.removeEventListener('resize', place);
        window.removeEventListener('scroll', onScroll, true);
        button.setAttribute('aria-expanded', 'false');
        wrap.classList.remove('is-open');
        layer.remove();
        if (open?.select === select) open = null;
    }
    open = { select, close };
}

export function enhanceSelects(root = document) {
    root.querySelectorAll('select.input').forEach(enhance);
}

export function startSelectEnhancer() {
    enhanceSelects();
    // Champs ajoutés plus tard (fenêtres, x-if, formulaires dynamiques).
    new MutationObserver((muts) => {
        for (const m of muts) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.matches?.('select.input')) enhance(n);
                else if (n.querySelector?.('select.input')) enhanceSelects(n);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
    // Valeur modifiée par le code (x-model, réinitialisation du formulaire) : l'affichage suit.
    setInterval(() => document.querySelectorAll('select.jc-select-native').forEach((s) => s._jcSync?.()), 400);
    document.addEventListener('reset', () => setTimeout(() => document.querySelectorAll('select.jc-select-native').forEach((s) => s._jcSync?.())), true);
}
