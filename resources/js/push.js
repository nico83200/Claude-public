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

/** Demande l'autorisation puis abonne l'appareil. Retourne 'granted' | 'denied' | 'default'. */
export async function subscribe(vapidKey, subscribeUrl) {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return permission;
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (sub && !sameKey(sub, vapidKey)) { await sub.unsubscribe(); sub = null; }
    sub ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(vapidKey) });
    const res = await sendJson(subscribeUrl, 'POST', sub.toJSON());
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'refus du serveur');
    return 'granted';
}

/**
 * Écran plein écran : sur téléphone/tablette (application installée), l'utilisation est bloquée
 * tant que les notifications ne sont pas activées. Jamais de blocage quand c'est impossible
 * (navigateur sans Web Push, ex. iOS < 16.4) : il n'y aurait aucun moyen d'en sortir.
 */
export function pushGateStore({ vapidKey, subscribeUrl, enabled }) {
    return {
        show: false,
        state: 'ask', // ask | denied
        busy: false,
        error: '',
        async init(install) {
            if (!install || !enabled || !install.handheld || !install.installed || !pushSupported() || !vapidKey) return;
            await this.check();
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') this.check(); });
        },
        async check() {
            try {
                if (Notification.permission === 'granted' && await syncSubscription(subscribeUrl, vapidKey)) { this.show = false; return; }
            } catch { /* service worker indisponible : on redemande */ }
            this.state = Notification.permission === 'denied' ? 'denied' : 'ask';
            this.show = true;
        },
        async enable() {
            this.busy = true; this.error = '';
            try {
                const result = await subscribe(vapidKey, subscribeUrl);
                if (result === 'granted') this.show = false;
                else this.state = result === 'denied' ? 'denied' : 'ask';
            } catch (e) {
                this.error = 'Activation impossible : ' + String(e?.message || 'erreur inconnue').replace(/\.$/, '') + '. Vérifiez la connexion puis réessayez.';
            } finally { this.busy = false; }
        },
    };
}
