// Installation de bout en bout depuis le zip de distribution (dossier vierge, base inexistante).
//   scripts/build-release.sh && unzip … && php artisan serve --port=8100 (dans le dossier décompressé)
//   E2E_BASE=http://localhost:8100 E2E_DB=jackcie_e2e node tests/e2e/installer.mjs
import { chromium } from 'playwright';

const BASE = process.env.E2E_BASE ?? 'http://localhost:8100';
const SHOTS = process.env.E2E_SHOTS ?? null;
const DB = { host: '127.0.0.1', port: '3306', database: process.env.E2E_DB ?? 'jackcie_e2e', username: process.env.E2E_DB_USER ?? 'equine', password: process.env.E2E_DB_PASS ?? 'secret' };
const assert = (c, m) => { if (!c) throw new Error('ÉCHEC : ' + m); console.log('• OK', m); };

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH ?? '/opt/pw-browsers/chromium' });
const page = await (await browser.newContext({ viewport: { width: 430, height: 932 }, deviceScaleFactor: 2 })).newPage();
const shot = async (n) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${n}.png`, fullPage: true }); };
try {
    await page.goto(BASE + '/');
    assert(page.url().endsWith('/install'), 'redirection automatique vers l\'installeur');
    await shot('1-prerequis');
    await page.click('text=Commencer l\'installation');

    await page.fill('#f_password', 'mauvais');
    for (const [k, v] of Object.entries(DB)) if (k !== 'password') await page.fill('#f_' + k, v);
    await page.check('input[name=create_database]');
    await page.click('button:has-text("Tester la connexion")');
    await page.waitForSelector('text=identifiant ou mot de passe refusé');
    assert(true, 'erreur de connexion expliquée');
    await page.fill('#f_password', DB.password);
    await page.check('input[name=create_database]');
    await shot('2-base');
    await page.click('button:has-text("Tester la connexion")');
    await page.waitForURL('**/install/application', { timeout: 120000 });
    assert(await page.isVisible('text=Base de données prête'), 'base créée et tables installées');

    await page.fill('#f_app_url', BASE);
    await page.fill('#f_support_email', 'aide@jackcie.test');
    await shot('3-application');
    await page.click('button:has-text("Enregistrer et continuer")');
    await page.waitForURL('**/install/administrateur');

    await page.fill('#f_name', 'Nicolas Admin');
    await page.fill('#f_email', 'admin@jackcie.test');
    await page.fill('#f_password', 'Jack&Cie-2026!');
    await page.fill('#f_password_confirmation', 'Jack&Cie-2026!');
    await shot('4-administrateur');
    await page.click('button:has-text("Créer le compte et terminer")');
    await page.waitForURL('**/install/termine**');
    assert(await page.isVisible('text=Installation terminée'), 'installation terminée');
    assert(await page.isVisible('text=schedule:run'), 'ligne cron fournie');
    await shot('5-termine');

    const r = await page.request.get(BASE + '/install');
    assert(r.status() === 404, 'installeur fermé définitivement');

    await page.click('text=Se connecter');
    await page.fill('#f_email', 'admin@jackcie.test');
    await page.fill('#f_password', 'Jack&Cie-2026!');
    await page.click('button:has-text("Se connecter")');
    await page.waitForURL('**/dashboard');
    assert(await page.isVisible('text=Bonjour Nicolas'), 'connexion du super-administrateur');
    await shot('6-tableau-de-bord');
    await page.goto(BASE + '/tarifs');
    assert(await page.isVisible('text=Découverte'), 'offres de référence installées');
    console.log('\nE2E installeur : SUCCÈS');
} catch (e) { await shot('erreur'); console.error(e); process.exitCode = 1; } finally { await browser.close(); }
