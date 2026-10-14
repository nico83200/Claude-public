// Captures d'écran de l'application pour le site jackcie.fr (base de démonstration locale uniquement).
// Prérequis : serveur de l'app sur http://127.0.0.1:8012 (et mock_claude.py pour l'assistant).
// Usage : node scripts/site/capture.mjs → site/img/app/*.png (converties ensuite en WebP).
import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://127.0.0.1:8012';
const OUT = new URL('../../site/img/app/', import.meta.url).pathname;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

// Retire les mentions « (démo) » et les éléments d'aide qui n'ont pas leur place sur une vitrine.
const tidy = async (p) => {
    await p.evaluate(() => {
        const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walk.nextNode()) walk.currentNode.nodeValue = walk.currentNode.nodeValue.replace(/\s*\(démo\)/g, '');
        document.querySelectorAll('[data-onboarding], [data-install-banner], [data-push-gate], aside a[href*="/admin"]').forEach((e) => e.remove());
        document.querySelectorAll('aside button').forEach((b) => { if (/Installer sur mon/.test(b.textContent)) b.remove(); });
        // Cartes d'aide du tableau de bord (fiche d'urgence à préparer, rappel de bilan).
        for (const t of ['Préparez la fiche d', 'Ne ratez plus un']) {
            document.querySelectorAll('main p, main h2, main h3, main strong').forEach((el) => {
                if (el.textContent.trim().startsWith(t)) (el.closest('section, .card, [class*="rounded"]:not(main)') || el).remove();
            });
        }
    });
    await p.waitForTimeout(300);
};

async function session(email, viewport, mobile) {
    const ctx = await browser.newContext({ viewport, deviceScaleFactor: 2, isMobile: mobile, hasTouch: mobile, locale: 'fr-FR', colorScheme: 'light' });
    await ctx.addInitScript(() => {
        try { localStorage.setItem('jc-install-dismissed', '1'); localStorage.setItem('jc-assistant-speech', '0'); localStorage.setItem('jc-theme', 'light'); } catch {}
    });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login');
    await p.fill('#f_email', email);
    await p.fill('#f_password', 'Demo2026!demo');
    await p.click('form button.btn-primary');
    await p.waitForURL(/dashboard/);
    return p;
}

async function shot(p, path, name, { full = false, before } = {}) {
    await p.goto(BASE + path);
    await p.waitForLoadState('networkidle');
    if (before) await before(p);
    await tidy(p);
    await p.screenshot({ path: OUT + name + '.png', fullPage: full });
    console.log('✓', name);
}

// Téléphone — propriétaire
const m = await session('proprietaire@demo.jackcie.test', { width: 390, height: 844 }, true);
await shot(m, '/dashboard', 'mobile-accueil');
await shot(m, '/chevaux/1', 'mobile-cheval');
await shot(m, '/chevaux/1/sante', 'mobile-sante');
await shot(m, '/calendrier', 'mobile-agenda');
await shot(m, '/seances/32', 'mobile-seance');
await shot(m, '/budget', 'mobile-budget');
await shot(m, '/chevaux/1/poids', 'mobile-poids');
await shot(m, '/dashboard', 'mobile-assistant', {
    before: async (p) => {
        await p.evaluate(() => Alpine.store('assistant').open());
        await p.waitForTimeout(600);
        await p.evaluate(() => Alpine.store('assistant').send('Combien m\'a coûté le maréchal cette année ?', 'text'));
        await p.waitForFunction(() => Alpine.store('assistant').state === 'result', null, { timeout: 15000 });
        await p.evaluate(() => Alpine.store('assistant').stopCountdown?.());
        await p.waitForTimeout(500);
    },
});
const token = process.env.EMERGENCY_TOKEN;
if (token) await shot(m, '/urgence/' + token, 'mobile-urgence');

// Ordinateur — propriétaire puis gérant d'écurie
const d = await session('proprietaire@demo.jackcie.test', { width: 1440, height: 900 }, false);
await shot(d, '/dashboard', 'desktop-accueil');
await shot(d, '/chevaux/1/sante', 'desktop-sante');
const g = await session('gerant@demo.jackcie.test', { width: 1440, height: 900 }, false);
await shot(g, '/dashboard', 'desktop-ecurie');
await shot(g, '/chevaux', 'desktop-ecurie-chevaux');
await shot(g, '/ecurie/pension', 'desktop-pension');
await shot(g, '/calendrier', 'desktop-ecurie-agenda');
const gm = await session('gerant@demo.jackcie.test', { width: 390, height: 844 }, true);
await shot(gm, '/ecurie/pension', 'mobile-pension');
await shot(gm, '/dashboard', 'mobile-ecurie');
await shot(gm, '/calendrier', 'mobile-ecurie-agenda');

await browser.close();
