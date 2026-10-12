// Page Paramètres › Notifications : activation, désactivation, essai sur cet appareil.
import { currentSubscription, pushSupported, reportState, sendJson, subscribe, syncSubscription } from './push.js';

export function pushSettings({ vapidKey, subscribeUrl, unsubscribeUrl, testUrl, stateUrl }) {
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
                this.state = (Notification.permission === 'granted' && await syncSubscription(subscribeUrl, vapidKey)) ? 'on' : 'off';
                if (this.state === 'on') reportState(stateUrl);
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
                // 1. Affichage local (sans passer par Internet) : vérifie que le téléphone ou le navigateur montre bien les notifications.
                let local = 'ok';
                try {
                    const reg = await navigator.serviceWorker.ready;
                    await reg.showNotification('Jack&Cie', { body: 'Essai d\'affichage sur cet appareil.', icon: '/icons/icon-192.png', tag: 'local_test' });
                } catch (e) { local = 'refusé : ' + String(e?.message || e); }
                await reportState(stateUrl, { local_test: local });
                // 2. Envoi par le service push (Apple, Google…), puis vérification que l'appareil l'a reçu.
                const started = Date.now();
                const res = await sendJson(testUrl, 'POST');
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.message = 'Aucun appareil n\'a pu être joint' + (data.error ? ' (' + data.error + ')' : '') + '. Désactivez puis réactivez les notifications.';
                    return;
                }
                this.message = 'Deux notifications d\'essai arrivent (une locale, une par Internet). Vérification de la réception…';
                let received = false;
                for (let i = 0; i < 6 && !received; i++) {
                    await new Promise((r) => setTimeout(r, 2500));
                    const state = await reportState(stateUrl);
                    received = Boolean(state?.received_at && Date.parse(state.received_at) >= started - 5000);
                }
                this.message = received
                    ? 'Notification reçue par cet appareil. Si elle n\'est pas apparue, autorisez les notifications de Jack&Cie dans les réglages du téléphone (et vérifiez le mode Concentration / Ne pas déranger).'
                    : (local === 'ok'
                        ? 'La notification locale fonctionne, mais celle envoyée par Internet n\'est pas arrivée sur cet appareil. Désactivez puis réactivez les notifications ci-dessus ; si cela persiste, signalez-le (Administration › Notifications affiche le détail).'
                        : 'Cet appareil refuse d\'afficher les notifications (' + local + '). Autorisez-les pour Jack&Cie dans les réglages du téléphone ou du navigateur.');
            } finally { this.busy = false; }
        },
    };
}
