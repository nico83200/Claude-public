// Installation de Jack&Cie en web app sur l'écran d'accueil.
// - Android / Chrome / Edge / ordinateur : invite native (événement beforeinstallprompt).
// - iPhone / iPad (Safari) : pas d'invite programmable → instructions guidées « Partager > Sur l'écran d'accueil ».
// - Déjà installée (mode standalone) : rien n'est proposé.
const DISMISS_KEY = 'jackcie-install-dismissed';
const DISMISS_DAYS = 30;

let deferredPrompt = null;

const ua = navigator.userAgent || '';
const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isAndroid = /Android/i.test(ua);
const isIOSSafari = isIOS && /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

function dismissedRecently() {
    try {
        const at = Number(localStorage.getItem(DISMISS_KEY) || 0);
        return at && Date.now() - at < DISMISS_DAYS * 864e5;
    } catch {
        return false;
    }
}

export function installStore() {
    return {
        canPrompt: false,
        installed: isStandalone(),
        isIOS,
        isIOSSafari,
        isAndroid,
        isMobile: isIOS || isAndroid || window.matchMedia('(max-width: 768px)').matches,
        dismissed: dismissedRecently(),
        sheet: false,

        /** Bandeau mobile : seulement si l'installation est possible et non refusée récemment. */
        get showBanner() {
            return !this.installed && !this.dismissed && this.isMobile && (this.canPrompt || this.isIOS || this.isAndroid);
        },
        get available() {
            return !this.installed && (this.canPrompt || this.isIOS || this.isAndroid);
        },

        async install() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                this.canPrompt = false;
                if (outcome === 'accepted') this.installed = true;
                return;
            }
            this.sheet = true; // iOS ou navigateur sans invite : instructions
        },

        dismiss() {
            this.dismissed = true;
            try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch { /* stockage indisponible */ }
        },
    };
}

export function listenForInstall(Alpine) {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault(); // on affiche notre propre bouton au bon moment
        deferredPrompt = event;
        Alpine.store('install').canPrompt = true;
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        Alpine.store('install').installed = true;
        Alpine.store('install').canPrompt = false;
    });
    window.matchMedia('(display-mode: standalone)').addEventListener?.('change', (e) => {
        Alpine.store('install').installed = e.matches;
    });
}
