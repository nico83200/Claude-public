// Captures téléphone pour les publications « nouveautés » (base de démonstration locale + faux Claude sur :8098).
// Usage : node scripts/instagram-nouveautes/capture.mjs  (application sur http://127.0.0.1:8012)
import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8012';
const OUT = new URL('./cap/', import.meta.url).pathname;
const PHOTO = new URL('../affiche-equitalyon/ecran.jpg', import.meta.url).pathname;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--autoplay-policy=no-user-gesture-required'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2.5, isMobile: true, hasTouch: true, locale: 'fr-FR', colorScheme: 'light' });
await ctx.addInitScript(() => { try { localStorage.setItem('jc-assistant-speech', '1'); localStorage.setItem('jc-theme', 'light'); } catch {} });
const p = await ctx.newPage();
const tidy = () => p.evaluate(() => {
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (w.nextNode()) w.currentNode.nodeValue = w.currentNode.nodeValue.replace(/\s*\(démo\)/g, '');
    // Adresse publique de l'application à la place de l'adresse de test.
    document.querySelectorAll('input[readonly]').forEach((i) => { i.value = i.value.replace(/^https?:\/\/127\.0\.0\.1:\d+/, 'https://app.jackcie.fr'); });
    for (const t of ['Préparez la fiche d', 'Ne ratez plus un']) document.querySelectorAll('main p, main h2, main strong').forEach((el) => { if (el.textContent.trim().startsWith(t)) (el.closest('section, .card, [class*="rounded"]:not(main)') || el).remove(); });
});
const shot = async (name) => { await tidy(); await p.waitForTimeout(400); await p.screenshot({ path: OUT + name + '.png' }); console.log('✓', name); };
await p.goto(BASE + '/login');
await p.fill('#f_email', 'proprietaire@demo.jackcie.test'); await p.fill('#f_password', 'Demo2026!demo');
await p.click('form button.btn-primary'); await p.waitForURL(/dashboard/);

// Ordonnance : photo envoyée, l'assistant a préparé le soin et les traitements.
await p.goto(BASE + '/chevaux/1/sante');
await p.setInputFiles('input[name="prescription"]', PHOTO);
await p.waitForURL(/ordonnance\//, { timeout: 20000 }); await p.waitForLoadState('networkidle');
await shot('ordonnance');
// Plan de séance proposé.
await p.goto(BASE + '/seances/32/preparer');
await p.click('[data-ai-plan-ask]');
await p.waitForSelector('[data-ai-plan] dialog[open]', { timeout: 20000 }); await p.waitForTimeout(600);
await shot('plan-seance');
// Résumé pour le vétérinaire, Ma semaine, parrainage, fiche d'urgence, poids.
for (const [path, name] of [['/chevaux/1/resume-veterinaire', 'resume-veto'], ['/parrainage', 'parrainage'], ['/chevaux/1/poids', 'poids']]) {
    await p.goto(BASE + path); await p.waitForLoadState('networkidle'); await shot(name);
}
const token = process.env.EMERGENCY_TOKEN;
if (token) { await p.goto(BASE + '/urgence/' + token); await p.waitForLoadState('networkidle'); await shot('urgence'); }
// Assistant : réponse affichée (et lue à voix haute).
await p.goto(BASE + '/dashboard');
await p.evaluate(() => Alpine.store('assistant').open()); await p.waitForTimeout(600);
await p.evaluate(() => Alpine.store('assistant').send('Combien m\'a coûté le maréchal cette année ?', 'voice'));
await p.waitForFunction(() => Alpine.store('assistant').state === 'result', null, { timeout: 15000 });
await p.waitForTimeout(2500); await p.evaluate(() => Alpine.store('assistant').stopCountdown?.()); // réponse lue (voix simulée), aucune erreur affichée
await shot('voix');
await b.close();
