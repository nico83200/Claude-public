// Notifications (Web Push) : fonctions partagées par la page Paramètres et l'écran d'activation.
const b64ToBytes = (s) => {
    const pad = '='.repeat((4 - (s.length % 4)) % 4);
    const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
};
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

export function sendJson(url, method, body) {
    return fetch(url, {
        method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: body ? JSON.stringify(body) : undefined,
    });
}

export async function currentSubscription() {
    const reg = await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
}

const sameKey = (sub, vapidKey) => {
    const raw = sub?.options?.applicationServerKey;
    if (!raw || !vapidKey) return true; // navigateur qui ne l'expose pas : on ne peut pas comparer
    const current = new Uint8Array(raw);
    const wanted = b64ToBytes(vapidKey);
    return current.length === wanted.length && current.every((v, i) => v === wanted[i]);
};

/**
 * Enregistre l'abonnement existant côté serveur (changement de compte, réinstallation).
 * Si la clé d'envoi du serveur a changé, l'ancien abonnement ne recevrait plus rien :
 * il est remplacé sans redemander l'autorisation (déjà accordée).
 */
export async function syncSubscription(subscribeUrl, vapidKey) {
    let sub = await currentSubscription();
    if (!sub) return null;
    if (!sameKey(sub, vapidKey)) {
        await sub.unsubscribe();
        const reg = await navigator.serviceWorker.ready;
        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(vapidKey) });
    }
    const res = await sendJson(subscribeUrl, 'POST', sub.toJSON());
    return res.ok ? sub : null;
}

/** Version du service worker actif (diagnostic). */
export function swVersion() {
    return navigator.serviceWorker.ready.then((reg) => new Promise((resolve) => {
        if (!reg.active) return resolve(null);
        const ch = new MessageChannel();
        const timer = setTimeout(() => resolve('ancienne'), 1500); // v7 et avant ne répondent pas
        ch.port1.onmessage = (e) => { clearTimeout(timer); resolve(String(e.data)); };
        reg.active.postMessage('version', [ch.port2]);
    })).catch(() => null);
}

/** Envoie l'état de cet appareil au serveur (autorisation, version, essai local) pour le diagnostic. */
export async function reportState(stateUrl, extra = {}) {
    if (!stateUrl) return null;
    try {
        const sub = await currentSubscription();
        if (!sub) return null;
        const res = await sendJson(stateUrl, 'POST', { endpoint: sub.endpoint, permission: Notification.permission, version: await swVersion(), ...extra });
        return res.ok ? res.json() : null;
    } catch { return null; }
}

/**
 * Demande l'autorisation puis abonne l'appareil. Retourne 'granted' | 'denied' | 'default'.
 * alreadyGranted : autorisation constatée par ailleurs (réglages du téléphone) ; on ne redemande pas,
 * car Notification.requestPermission() peut encore répondre « denied » dans une page ouverte avant le changement.
 */
export async function subscribe(vapidKey, subscribeUrl, alreadyGranted = false) {
    const permission = alreadyGranted ? 'granted' : await Notification.requestPermission();
    if (permission !== 'granted') return permission;
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (sub && !sameKey(sub, vapidKey)) { await sub.unsubscribe(); sub = null; }
    sub ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(vapidKey) });
    const res = await sendJson(subscribeUrl, 'POST', sub.toJSON());
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'refus du serveur');
    return 'granted';
}

/** Clé de session (le navigateur peut la refuser : navigation privée, stockage bloqué). */
const session = {
    get: (k) => { try { return sessionStorage.getItem(k); } catch { return null; } },
    set: (k, v) => { try { v === null ? sessionStorage.removeItem(k) : sessionStorage.setItem(k, v); } catch { /* sans effet */ } },
};

/** « Plus tard » : l'écran ne revient qu'au bout de quelques jours (stockage local, sinon pour la session). */
const LATER_DAYS = 7;
const later = {
    active() {
        try { const t = Number(localStorage.getItem('jc-push-later') || 0); return t > 0 && Date.now() - t < LATER_DAYS * 86400000; } catch { return !!session.get('jc-push-skip'); }
    },
    set() { try { localStorage.setItem('jc-push-later', String(Date.now())); } catch { session.set('jc-push-skip', '1'); } },
};

/**
 * Autorisation actuelle. Notification.permission peut rester figée sur « denied » dans une page déjà
 * ouverte quand l'utilisateur autorise ensuite dans les réglages d'Android : l'API Permissions est
 * consultée aussi, et l'une ou l'autre suffit pour « granted ».
 */
export async function notificationPermission() {
    let queried = null;
    try { queried = (await navigator.permissions?.query({ name: 'notifications' }))?.state ?? null; } catch { /* non pris en charge */ }
    if (Notification.permission === 'granted' || queried === 'granted') return 'granted';
    if (Notification.permission === 'denied' || queried === 'denied') return 'denied';
    return 'default';
}

/**
 * Écran plein écran : sur téléphone/tablette (application installée), l'utilisation est bloquée
 * tant que les notifications ne sont pas activées. Jamais de blocage quand c'est impossible
 * (navigateur sans Web Push, ex. iOS < 16.4) : il n'y aurait aucun moyen d'en sortir.
 * « Plus tard » est toujours proposé : l'écran revient après quelques jours (LATER_DAYS), jamais avant
 * que le premier cheval soit ajouté (le serveur ne l'active qu'à ce moment-là).
 */
export function pushGateStore({ vapidKey, subscribeUrl, enabled, stateUrl }) {
    return {
        show: false,
        state: 'ask', // ask | denied
        busy: false,
        stuck: false, // toujours refusé après rechargement : aide détaillée + « continuer sans »
        error: '',
        async init(install) {
            if (!install || !enabled || !install.handheld || !install.installed || !pushSupported() || !vapidKey) return;
            if (session.get('jc-push-skip') || later.active()) return;
            this.stuck = session.get('jc-push-retry') === '1';
            await this.check();
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && this.show) this.check(); });
            try {
                const status = await navigator.permissions?.query({ name: 'notifications' });
                if (status) status.onchange = () => this.show && this.check();
            } catch { /* non pris en charge */ }
        },
        done() {
            this.show = false; this.error = '';
            session.set('jc-push-retry', null);
            reportState(stateUrl);
        },
        async check() {
            const permission = await notificationPermission();
            if (permission === 'granted') {
                try {
                    // Autorisation déjà donnée (réglages du téléphone) : abonnement direct, sans autre écran.
                    if (await syncSubscription(subscribeUrl, vapidKey) || await subscribe(vapidKey, subscribeUrl, true) === 'granted') return this.done();
                } catch (e) {
                    this.error = 'Activation impossible : ' + String(e?.message || 'erreur inconnue').replace(/\.$/, '') + '. Vérifiez la connexion puis réessayez.';
                }
            }
            this.state = permission === 'denied' ? 'denied' : 'ask';
            this.show = true;
        },
        async enable() {
            this.busy = true; this.error = '';
            try {
                const result = await subscribe(vapidKey, subscribeUrl);
                if (result === 'granted') this.done();
                else this.state = result === 'denied' ? 'denied' : 'ask';
            } catch (e) {
                this.error = 'Activation impossible : ' + String(e?.message || 'erreur inconnue').replace(/\.$/, '') + '. Vérifiez la connexion puis réessayez.';
            } finally { this.busy = false; }
        },
        /** « C'est fait, continuer » : revérifie ; si rien n'a changé pour la page, elle est rechargée une fois. */
        async retry() {
            this.busy = true; this.error = '';
            try { await this.check(); } finally { this.busy = false; }
            if (!this.show) return;
            if (session.get('jc-push-retry') !== '1') {
                session.set('jc-push-retry', '1');
                this.busy = true;
                return location.reload();
            }
            if (this.state === 'ask') return this.enable();
            this.stuck = true;
            this.error = 'Les notifications sont toujours bloquées pour Jack&Cie. Suivez les étapes ci-dessous, puis réessayez.';
        },
        skip() {
            session.set('jc-push-skip', '1');
            later.set();
            this.show = false;
        },
    };
}
