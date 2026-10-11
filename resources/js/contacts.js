/**
 * Fiches contact du téléphone → intervenant.
 *
 * - Android (Chrome) : sélecteur de contacts du système (Contact Picker API), la personne choisit
 *   une fiche ; rien d'autre n'est lu.
 * - iPhone et autres : fichier vCard (.vcf) obtenu par « Partager le contact » › Enregistrer dans Fichiers.
 * Les informations remplissent le formulaire ; rien n'est enregistré avant validation.
 */

export const contactPickerSupported = () => 'contacts' in navigator && 'ContactsManager' in window;

const KIND_WORDS = [
    ['veterinarian', /v[ée]t[ée]rinaire|\bv[ée]to\b|clinique v/i],
    ['farrier', /mar[ée]chal|ferrant|parage/i],
    ['osteopath', /ost[ée]o/i],
    ['dentist', /dentist/i],
    ['instructor', /moniteur|monitrice|enseignant|coach|instructeur|instructrice|cavalier pro|bpjeps|desjeps/i],
    ['saddler', /sellier|sellerie/i],
    ['nutritionist', /nutrition/i],
];

export function guessKind(...texts) {
    const t = texts.filter(Boolean).join(' ');
    return KIND_WORDS.find(([, re]) => re.test(t))?.[0] ?? null;
}

function splitName(full) {
    const parts = String(full || '').trim().split(/\s+/);
    if (parts.length < 2) return { first_name: '', last_name: parts[0] || '' };
    return { first_name: parts.slice(0, -1).join(' '), last_name: parts.at(-1) };
}

const unescapeV = (s) => s.replace(/\\n/gi, '\n').replace(/\\([,;\\])/g, '$1').trim();

/** Lit un fichier vCard (2.1, 3.0 ou 4.0) : renvoie une liste de contacts. */
export function parseVCard(text) {
    // Lignes repliées (RFC 6350 § 3.2) et quoted-printable « soft breaks » (vCard 2.1).
    const lines = text.replace(/\r\n|\r/g, '\n').replace(/\n[ \t]/g, '').replace(/=\n/g, '').split('\n');
    const cards = [];
    let card = null;
    for (const raw of lines) {
        const line = raw.trim();
        if (/^BEGIN:VCARD$/i.test(line)) { card = { tel: [], email: [] }; continue; }
        if (/^END:VCARD$/i.test(line)) { if (card) cards.push(card); card = null; continue; }
        if (!card || !line.includes(':')) continue;
        const idx = line.indexOf(':');
        const head = line.slice(0, idx);
        let value = line.slice(idx + 1);
        const [prop, ...params] = head.split(';');
        const name = prop.replace(/^item\d+\./i, '').toUpperCase();
        const p = params.join(';').toUpperCase();
        if (p.includes('QUOTED-PRINTABLE')) {
            try { value = decodeURIComponent(value.replace(/=([0-9A-F]{2})/gi, '%$1')); } catch { /* valeur gardée telle quelle */ }
        }
        const pref = /PREF/.test(p) || /TYPE=[^;]*PREF/.test(p);
        switch (name) {
            case 'FN': card.fn = unescapeV(value); break;
            case 'N': {
                const [last, first] = value.split(';').map(unescapeV);
                card.n = { last_name: last || '', first_name: first || '' };
                break;
            }
            case 'ORG': card.org = unescapeV(value.split(';')[0]); break;
            case 'TITLE': case 'ROLE': card.title = [card.title, unescapeV(value)].filter(Boolean).join(' '); break;
            case 'TEL': (pref ? card.tel.unshift.bind(card.tel) : card.tel.push.bind(card.tel))(unescapeV(value.replace(/^tel:/i, ''))); break;
            case 'EMAIL': (pref ? card.email.unshift.bind(card.email) : card.email.push.bind(card.email))(unescapeV(value)); break;
            case 'ADR': if (!card.adr) {
                const [, , street, city, , zip, country] = value.split(';').map(unescapeV);
                card.adr = [street, [zip, city].filter(Boolean).join(' '), country && !/^france$/i.test(country) ? country : ''].filter(Boolean).join(', ');
            } break;
            case 'NOTE': card.note = unescapeV(value); break;
            default:
        }
    }
    return cards.map((c) => {
        const companyOnly = !(c.n && (c.n.last_name || c.n.first_name)) && c.org && (!c.fn || c.fn === c.org);
        const name = c.n && (c.n.last_name || c.n.first_name) ? c.n : (companyOnly ? { first_name: '', last_name: c.org } : splitName(c.fn || c.org));
        if (!name.last_name && name.first_name) { name.last_name = name.first_name; name.first_name = ''; }
        return {
            ...name,
            company: c.org && c.org !== c.fn ? c.org : '',
            phone: c.tel[0] || '',
            email: (c.email[0] || '').toLowerCase(),
            address: c.adr || '',
            notes: c.note || '',
            kind: guessKind(c.title, c.org, c.note, c.fn),
            display: c.fn || [name.first_name, name.last_name].filter(Boolean).join(' ') || c.org || 'Contact',
        };
    }).filter((c) => c.last_name || c.company);
}

/** Ouvre le sélecteur de contacts du téléphone (une fiche). */
export async function pickContact() {
    const props = ['name', 'email', 'tel'];
    try {
        const supported = await navigator.contacts.getProperties();
        if (supported.includes('address')) props.push('address');
    } catch { /* propriétés par défaut */ }
    const [c] = await navigator.contacts.select(props, { multiple: false });
    if (!c) return null;
    const name = splitName(c.name?.[0]);
    const a = c.address?.[0];
    return {
        ...name,
        company: '',
        phone: c.tel?.[0] || '',
        email: (c.email?.[0] || '').toLowerCase(),
        address: a ? [(a.addressLine || []).join(' '), [a.postalCode, a.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') : '',
        notes: '',
        kind: guessKind(c.name?.[0]),
        display: c.name?.[0] || name.last_name,
    };
}

export async function readVCardFile(file) {
    if (!file) return [];
    if (file.size > 2 * 1024 * 1024) throw new Error('Fichier trop volumineux pour une fiche contact.');
    return parseVCard(await file.text());
}

/**
 * Formulaire d'intervenant (fenêtre de création rapide ou page « Nouvel intervenant ») :
 * remplit les champs à partir d'une fiche contact.
 */
export function contactImport() {
    return {
        pickerSupported: contactPickerSupported(),
        importError: '',
        imported: '',
        choices: [],
        async pick() {
            this.importError = '';
            try {
                const c = await pickContact();
                if (c) this.fill(c);
            } catch {
                this.importError = 'Accès aux contacts refusé ou indisponible. Vous pouvez importer une fiche .vcf.';
            }
        },
        async file(e) {
            this.importError = '';
            try {
                const cards = await readVCardFile(e.target.files[0]);
                if (!cards.length) this.importError = 'Aucune fiche contact lisible dans ce fichier.';
                else if (cards.length === 1) this.fill(cards[0]);
                else this.choices = cards; // plusieurs contacts dans le fichier : la personne choisit
            } catch (err) {
                this.importError = err.message || 'Fichier illisible.';
            }
            e.target.value = '';
        },
        fill(c) {
            this.choices = [];
            const form = this.$root.querySelector('form') || this.$root.closest('form');
            ['first_name', 'last_name', 'company', 'phone', 'email', 'address', 'notes'].forEach((k) => {
                const input = form?.querySelector(`[name="${k}"]`);
                if (input && c[k]) { input.value = c[k]; input.dispatchEvent(new Event('input', { bubbles: true })); }
            });
            const kind = form?.querySelector('[name="kind"]');
            if (kind && c.kind) { kind.value = c.kind; kind.dispatchEvent(new Event('change', { bubbles: true })); }
            this.imported = c.display;
        },
    };
}

/**
 * Fenêtre « Nouvel intervenant » ouverte depuis un formulaire : enregistre la fiche (ou met à jour
 * la fiche existante correspondante) et la sélectionne aussitôt dans le champ d'origine.
 */
export function contactFormStore(config) {
    return {
        isOpen: false,
        saving: false,
        error: '',
        org: null,
        kind: 'veterinarian',
        target: null,
        mode: 'select',
        open({ org = null, kind = null, target = null, mode = 'select' } = {}) {
            this.org = org;
            this.kind = kind || 'veterinarian';
            this.target = target;
            this.mode = mode;
            this.error = '';
            this.isOpen = true;
            const dialog = document.getElementById('contact-dialog');
            dialog?.querySelector('form')?.reset();
            const k = dialog?.querySelector('[name="kind"]');
            if (k) { k.value = this.kind; k.dispatchEvent(new Event('change', { bubbles: true })); }
            dialog?.showModal();
        },
        close() {
            this.isOpen = false;
            document.getElementById('contact-dialog')?.close();
        },
        async save(form) {
            this.saving = true;
            this.error = '';
            const body = new FormData(form);
            if (this.org) body.set('organization_id', this.org);
            try {
                const res = await fetch(config.url, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf } });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Enregistrement impossible.');
                    return;
                }
                this.apply(data);
                this.close();
                window.dispatchEvent(new CustomEvent('jc-toast', { detail: data.message }));
            } catch {
                this.error = 'Pas de connexion : réessayez.';
            } finally {
                this.saving = false;
            }
        },
        apply(data) {
            const target = this.target && document.querySelector(this.target);
            if (!target) return;
            if (target.tagName === 'SELECT') {
                let opt = [...target.options].find((o) => o.value === String(data.id));
                if (!opt) { opt = new Option(data.label, data.id); target.add(opt); }
                target.value = String(data.id);
            } else {
                target.value = data.name; // champ texte (enseignant d'un cours)
                const list = target.list;
                if (list && ![...list.options].some((o) => o.value === data.name)) list.appendChild(new Option(data.name));
            }
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            target._jcSync?.();
        },
    };
}
