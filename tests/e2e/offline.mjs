// Test de bout en bout du mode hors ligne dans Chromium (PWA réelle).
// Prérequis : base de démonstration chargée et serveur lancé :
//   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
//   php artisan serve --port=8000
//   node tests/e2e/offline.mjs
import { chromium, devices } from 'playwright';

const BASE = process.env.E2E_BASE ?? 'http://localhost:8000';
const SHOTS = process.env.E2E_SHOTS ?? null;
const log = (...a) => console.log('•', ...a);
const assert = (cond, msg) => { if (!cond) { throw new Error('ÉCHEC : ' + msg); } log('OK', msg); };

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH ?? '/opt/pw-browsers/chromium' });
const context = await browser.newContext({ ...devices['Pixel 7'], serviceWorkers: 'allow' });
const page = await context.newPage();
page.on('pageerror', (e) => console.error('Erreur JS :', e.message));
const shot = async (name) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true }); };

try {
    // 1. Connexion de la demi-pension
    await page.goto(`${BASE}/login`);
    await page.fill('#f_email', 'cavaliere@demo.jackcie.test');
    await page.fill('#f_password', 'Demo2026!demo');
    await page.click('button:has-text("Se connecter")');
    await page.waitForURL('**/dashboard');
    await shot('01-dashboard');
    assert(await page.isVisible('text=Uni'), 'tableau de bord affiche le cheval partagé');

    // 2. Rendre le cheval disponible hors ligne
    await page.click('text=Uni');
    await page.waitForURL(/\/chevaux\/\d+$/);
    const horseId = page.url().match(/chevaux\/(\d+)/)[1];
    await page.click('button:has-text("Disponible hors ligne")');
    await page.waitForSelector('text=Le cheval sera disponible hors ligne');

    // 3. Mode écurie en ligne : téléchargement des données + installation du Service Worker
    await page.goto(`${BASE}/hors-ligne`);
    await page.waitForFunction(() => navigator.serviceWorker.ready.then(() => true));
    await page.reload(); // la page est désormais contrôlée par le Service Worker
    await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    await page.waitForSelector('text=UNIVERS DES TILLEULS', { timeout: 15000 }).catch(() => {});
    await page.waitForSelector('main >> text=Uni');
    await page.waitForSelector('text=Synchronisé');
    await shot('02-ecurie-en-ligne');
    assert(true, 'données téléchargées et service worker actif');

    // 4. Coupure du réseau
    await context.setOffline(true);
    await page.reload();
    await page.waitForSelector('text=Hors ligne');
    assert(await page.isVisible('main >> text=Uni'), 'la coquille et les données sont disponibles sans réseau');

    // 5. Création d'une séance hors ligne
    await page.click('main >> text=Uni');
    await page.click('button:has-text("Séance")');
    await page.fill('#ns-obj', 'Séance E2E hors ligne');
    await page.click('button:has-text("Créer et préparer")');
    await page.waitForSelector('text=Séance enregistrée sur l\'appareil');
    await page.locator('button:has-text("Exercice")').nth(1).click(); // phase « Travail principal »
    await page.fill('input[placeholder="Rechercher un exercice"]', 'cercle');
    await page.locator('section:visible button:has-text("Cercles")').first().click();
    await page.getByRole('button', { name: 'Terminé', exact: true }).click();
    await page.click('button:has-text("Mode séance")');
    await page.locator('button:has-text("Marquer fait")').first().click();
    await page.locator('button[aria-label="Plus une répétition"]').first().click();
    await page.locator('button[aria-label="Plus une répétition"]').first().click();
    await shot('03-mode-seance-hors-ligne');
    await page.click('button:has-text("Terminer et faire le bilan")');
    await page.fill('#db-progress', 'Très bonne écoute');
    await page.click('button:has-text("Enregistrer le bilan")');
    await page.waitForSelector('text=Bilan enregistré');
    const pending = await page.evaluate(() => Alpine.$data(document.querySelector('[x-data="stableApp"]')).state.pending);
    assert(pending >= 4, `opérations en attente enregistrées localement (${pending})`);

    // 6. Fermeture brutale / rechargement : rien n'est perdu
    await page.reload();
    await page.waitForSelector('text=Hors ligne');
    const after = await page.evaluate(() => Alpine.$data(document.querySelector('[x-data="stableApp"]')).state.pending);
    assert(after === pending, 'la file d\'attente survit au rechargement');
    await page.click('button:has-text("Synchronisation")');
    await shot('04-file-attente');

    // 7. Retour du réseau : synchronisation automatique
    await context.setOffline(false);
    await page.waitForFunction(() => Alpine.$data(document.querySelector('[x-data="stableApp"]')).state.pending === 0, null, { timeout: 20000 });
    await page.waitForSelector('text=Synchronisé');
    await shot('05-synchronise');
    assert(true, 'synchronisation terminée, plus aucune opération en attente');

    // 8. Vérification côté serveur (version web)
    await page.goto(`${BASE}/seances?horse=${horseId}`);
    assert(await page.isVisible('text=Séance E2E hors ligne'), 'la séance créée hors ligne existe sur le serveur');
    await page.click('a:has-text("Séance E2E hors ligne")');
    await page.waitForURL(/\/seances\/\d+$/);
    assert(await page.isVisible('text=Terminée'), 'séance clôturée');
    assert(await page.isVisible('text=Très bonne écoute'), 'bilan synchronisé');
    assert(await page.isVisible('text=2 rép. faites'), 'exercice réalisé et répétitions synchronisés');
    await shot('06-seance-serveur');

    // 9. Déconnexion : purge des données locales
    await page.goto(`${BASE}/dashboard`);
    await page.click('button[aria-label="Ouvrir le menu"]');
    await page.click('button:has-text("Se déconnecter")');
    await page.waitForURL(`${BASE}/`);
    const dbs = await page.evaluate(() => indexedDB.databases().then((l) => l.map((d) => d.name)));
    assert(!dbs.some((n) => n.startsWith('jackcie-u')), 'données locales purgées à la déconnexion');

    console.log('\nE2E hors ligne : SUCCÈS');
} catch (e) {
    await shot('erreur');
    console.error(e);
    process.exitCode = 1;
} finally {
    await browser.close();
}
