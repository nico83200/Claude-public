// Installation de Jack&Cie en web app sur l'écran d'accueil.
// - Android / Chrome / Edge / ordinateur : invite native (événement beforeinstallprompt).
// - iPhone / iPad (Safari) : pas d'invite programmable → instructions guidées « Partager > Sur l'écran d'accueil ».
// - Déjà installée (mode standalone) : rien n'est proposé.
const DISMISS_KEY = 'jackcie-install-dismissed';
const DISMISS_DAYS = 30;

let deferredPrompt = null;
// Délai d'attente de l'invite native après un toucher sur « Installer » (le geste reste valable environ 5 s).
const PROMPT_WAIT_MS = 4000;
const waitForPrompt = () => new Promise((resolve) => {
    if (deferredPrompt) return resolve(deferredPrompt);
    const done = () => { clearTimeout(timer); window.removeEventListener('jc-installable', done); resolve(deferredPrompt); };
    const timer = setTimeout(done, PROMPT_WAIT_MS);
    window.addEventListener('jc-installable', done);
});

const ua = navigator.userAgent || '';
const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isAndroid = /Android/i.test(ua);
const isIOSSafari = isIOS && /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
// Téléphone ou tablette (iPad récent : se présente comme un Mac tactile).
const isHandheld = /Android|iPhone|iPad|iPod|Mobile|Tablet|Silk|Kindle|PlayBook|Opera Mini|IEMobile/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
// Navigateurs intégrés aux réseaux sociaux / messageries : l'installation y est impossible.
const isInAppBrowser = /FBAN|FBAV|Instagram|LinkedInApp|Snapchat|Line\/|MicroMessenger|; wv\)|TikTok|Pinterest|GSA\//i.test(ua);
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
        handheld: isHandheld,
        inApp: isInAppBrowser,
        dismissed: dismissedRecently(),
        sheet: false,
        preparing: false,
        justInstalled: false,
        isSamsung: /SamsungBrowser/i.test(ua),

        /** Bandeau mobile : seulement si l'installation est possible et non refusée récemment. */
        get showBanner() {
            return !this.installed && !this.dismissed && this.isMobile && (this.canPrompt || this.isIOS || this.isAndroid);
        },
        /** Téléphone/tablette hors application installée : on propose uniquement l'installation. */
        get gate() {
            return this.handheld && !this.installed;
        },
        get available() {
            return !this.installed && (this.canPrompt || this.isIOS || this.isAndroid);
        },

        async install() {
            // Android / ordinateur : l'invite native installe la vraie application (WebAPK sur Android).
            // Si elle n'est pas encore prête, on l'attend quelques secondes plutôt que d'envoyer vers
            // « Ajouter à l'écran d'accueil », qui peut ne créer qu'un raccourci.
            if (!deferredPrompt && !this.isIOS && 'onbeforeinstallprompt' in window) {
                this.preparing = true;
                await waitForPrompt();
                this.preparing = false;
            }
            if (deferredPrompt) {
                const prompt = deferredPrompt;
                deferredPrompt = null;
                this.canPrompt = false;
                try {
                    await prompt.prompt();
                    const { outcome } = await prompt.userChoice;
                    if (outcome === 'accepted') this.justInstalled = true;
                    return;
                } catch { /* geste expiré : instructions ci-dessous */ }
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
    const capture = () => {
        if (!window.__jcInstall) return;
        deferredPrompt = window.__jcInstall;
        Alpine.store('install').canPrompt = true;
    };
    capture(); // invite arrivée avant le chargement de ce script
    window.addEventListener('jc-installable', capture);
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.__jcInstall = null;
        // Dans le navigateur, on invite à ouvrir l'application (le contenu reste réservé à l'application installée).
        Alpine.store('install').justInstalled = true;
        Alpine.store('install').canPrompt = false;
    });
    window.matchMedia('(display-mode: standalone)').addEventListener?.('change', (e) => {
        Alpine.store('install').installed = e.matches;
    });
}
