// Retour au même endroit après une saisie : la page d'où l'on part (liste des dépenses, carnet de santé…)
// et la ligne touchée sont retenues ; après l'enregistrement, l'appli revient sur cette page et fait défiler
// jusqu'à cette ligne (surlignée un instant). Rien ne change pour une navigation ordinaire (liens, retour arrière).
const KEY = 'jc-return';
export const MAX_AGE = 30 * 60 * 1000;

const path = (here) => here.split('?')[0].split('#')[0];

/** Adresse interne (chemin + recherche) d'un lien ou d'un formulaire, null si externe ou simple ancre. */
export function internal(href, base) {
    try {
        const u = new URL(href, base);
        const b = new URL(base);
        if (u.origin !== b.origin) return null;
        if (u.pathname === b.pathname && u.search === b.search && u.hash) return null;
        return u.pathname + u.search;
    } catch { return null; }
}

/**
 * Lien suivi ou formulaire envoyé depuis `here` vers `target` : on prolonge le parcours parti d'une page
 * (liste → fiche → modification), ou l'on en commence un nouveau depuis la page courante.
 */
export function nextRecord(rec, here, target, now, submitted = false) {
    const herePath = path(here);
    if (rec && now - rec.t < MAX_AGE && rec.chain.includes(herePath) && herePath !== rec.originPath) {
        return { ...rec, chain: [...new Set([...rec.chain, path(target)])], t: now, submitted: rec.submitted || submitted };
    }

    return { origin: here, originPath: herePath, row: path(target), chain: [path(target)], t: now, submitted };
}

/** Page d'origine à rejoindre après l'enregistrement d'une modification faite plus loin dans le parcours. */
export function returnTarget(rec, herePath, now) {
    return rec && now - rec.t < MAX_AGE && rec.chain.includes(herePath) && herePath !== rec.originPath ? rec.origin : null;
}

/** De retour sur la page d'origine après un enregistrement (pas après un simple retour arrière). */
export function shouldRestore(rec, here, now, navType) {
    return !! rec && rec.submitted && navType !== 'back_forward' && now - rec.t < MAX_AGE && path(here) === rec.originPath;
}

const read = () => { try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch { return null; } };
const write = (rec) => { try { rec ? sessionStorage.setItem(KEY, JSON.stringify(rec)) : sessionStorage.removeItem(KEY); } catch { /* navigation privée */ } };
const here = () => location.pathname + location.search;

function rowFor(target) {
    const same = (attr) => (el) => internal(el.getAttribute(attr) || '', location.href)?.split('?')[0] === target;
    const el = [...document.querySelectorAll('a[href]')].find(same('href')) || [...document.querySelectorAll('form[action]')].find(same('action'));

    return el ? (el.closest('tr, li, [data-swipe-row], [data-row], .card') || el) : null;
}

function restore(rec) {
    let tries = 0;
    const attempt = () => {
        const row = rowFor(rec.row);
        if (! row && tries++ < 8) return setTimeout(attempt, 120); // lignes affichées par Alpine un peu après le chargement
        if (row) {
            row.scrollIntoView({ block: 'center' });
            row.classList.add('jc-returned');
            setTimeout(() => row.classList.remove('jc-returned'), 1800);
        } else if (rec.y) {
            window.scrollTo(0, rec.y);
        }
    };
    attempt();
}

// Position de défilement retenue sur la page d'origine seulement (repli si la ligne n'y est plus).
const withY = (rec) => (rec.originPath === location.pathname ? { ...rec, y: window.scrollY } : rec);

export function startReturnPosition() {
    const nav = performance.getEntriesByType?.('navigation')?.[0]?.type;
    const rec = read();
    if (shouldRestore(rec, here(), Date.now(), nav)) {
        write(null);
        if (document.readyState === 'complete') restore(rec); else window.addEventListener('load', () => restore(rec), { once: true });
    }

    document.addEventListener('click', (e) => {
        const a = e.target.closest?.('a[href]');
        if (! a || a.target === '_blank' || a.hasAttribute('download') || e.defaultPrevented || e.metaKey || e.ctrlKey) return;
        const target = internal(a.getAttribute('href'), location.href);
        if (target) write(withY(nextRecord(read(), here(), target, Date.now())));
    }, true);

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (e.defaultPrevented || ! (form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        const target = internal(form.getAttribute('action') || location.href, location.href);
        if (! target) return;
        const prev = read();
        const method = (form.querySelector('input[name="_method"]')?.value || 'POST').toUpperCase();
        // Modification (formulaire marqué) ou suppression faite plus loin que la page d'origine : on y retourne.
        const back = returnTarget(prev, location.pathname, Date.now());
        if (back && (method === 'DELETE' || (form.hasAttribute('data-return-origin') && ['PUT', 'PATCH'].includes(method)))) {
            let field = form.querySelector('input[name="_return"]');
            if (! field) { field = document.createElement('input'); field.type = 'hidden'; field.name = '_return'; form.appendChild(field); }
            field.value = back;
        }
        write(withY(nextRecord(prev, here(), target, Date.now(), true)));
    });
}
