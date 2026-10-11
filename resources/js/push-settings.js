// Notifications sur cet appareil (Web Push) : activation, désactivation, essai.
const b64ToBytes = (s) => {
    const pad = '='.repeat((4 - (s.length % 4)) % 4);
    const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
};

export function pushSettings({ vapidKey, subscribeUrl, unsubscribeUrl, testUrl }) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
    const send = (url, method, body) => fetch(url, {
        method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: body ? JSON.stringify(body) : undefined,
    });

    return {
        state: 'checking', // checking | unsupported | install | denied | off | on
        busy: false,
        message: '',
        async init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
            const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.state = ios && !standalone ? 'install' : 'unsupported';
                return;
            }
            if (Notification.permission === 'denied') { this.state = 'denied'; return; }
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                this.state = sub ? 'on' : 'off';
                // Abonnement présent dans le navigateur : on le (ré)enregistre côté serveur (changement de compte, clés renouvelées).
                if (sub) await send(subscribeUrl, 'POST', sub.toJSON());
            } catch { this.state = 'off'; }
        },
        async enable() {
            this.busy = true; this.message = '';
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') { this.state = permission === 'denied' ? 'denied' : 'off'; return; }
                const reg = await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                if (sub && sub.options?.applicationServerKey) {
                    const current = new Uint8Array(sub.options.applicationServerKey);
                    const wanted = b64ToBytes(vapidKey);
                    if (current.length !== wanted.length || current.some((v, i) => v !== wanted[i])) { await sub.unsubscribe(); sub = null; }
                }
                sub ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(vapidKey) });
                const res = await send(subscribeUrl, 'POST', sub.toJSON());
                if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'refus du serveur');
                this.state = 'on';
                this.message = 'Notifications activées sur cet appareil.';
            } catch (e) {
                this.message = 'Activation impossible : ' + String(e?.message || 'erreur inconnue').replace(/\.$/, '') + '.';
            } finally { this.busy = false; }
        },
        async disable() {
            this.busy = true; this.message = '';
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) { await send(unsubscribeUrl, 'DELETE', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
                this.state = 'off';
                this.message = 'Notifications désactivées sur cet appareil.';
            } finally { this.busy = false; }
        },
        async test() {
            this.busy = true; this.message = '';
            try {
                const res = await send(testUrl, 'POST');
                this.message = res.ok ? 'Notification envoyée : elle doit apparaître dans quelques secondes.' : 'Aucun appareil n\'a pu être joint. Désactivez puis réactivez les notifications.';
            } finally { this.busy = false; }
        },
    };
}
