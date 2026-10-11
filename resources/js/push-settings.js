// Page Paramètres › Notifications : activation, désactivation, essai sur cet appareil.
import { currentSubscription, pushSupported, sendJson, subscribe, syncSubscription } from './push.js';

export function pushSettings({ vapidKey, subscribeUrl, unsubscribeUrl, testUrl }) {
    return {
        state: 'checking', // checking | unsupported | install | denied | off | on
        busy: false,
        message: '',
        async init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
            const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            if (!pushSupported()) { this.state = ios && !standalone ? 'install' : 'unsupported'; return; }
            if (Notification.permission === 'denied') { this.state = 'denied'; return; }
            try {
                this.state = (Notification.permission === 'granted' && await syncSubscription(subscribeUrl)) ? 'on' : 'off';
            } catch { this.state = 'off'; }
        },
        async enable() {
            this.busy = true; this.message = '';
            try {
                const result = await subscribe(vapidKey, subscribeUrl);
                this.state = result === 'granted' ? 'on' : (result === 'denied' ? 'denied' : 'off');
                if (result === 'granted') this.message = 'Notifications activées sur cet appareil.';
            } catch (e) {
                this.message = 'Activation impossible : ' + String(e?.message || 'erreur inconnue').replace(/\.$/, '') + '.';
            } finally { this.busy = false; }
        },
        async disable() {
            this.busy = true; this.message = '';
            try {
                const sub = await currentSubscription();
                if (sub) { await sendJson(unsubscribeUrl, 'DELETE', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
                this.state = 'off';
                this.message = 'Notifications désactivées sur cet appareil.';
            } finally { this.busy = false; }
        },
        async test() {
            this.busy = true; this.message = '';
            try {
                const res = await sendJson(testUrl, 'POST');
                this.message = res.ok ? 'Notification envoyée : elle doit apparaître dans quelques secondes.' : 'Aucun appareil n\'a pu être joint. Désactivez puis réactivez les notifications.';
            } finally { this.busy = false; }
        },
    };
}
