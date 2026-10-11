import { chromium, devices } from 'playwright';
const [,, out] = process.argv;
const B = 'http://127.0.0.1:8011';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function session(email) {
  const ctx = await b.newContext({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 }, deviceScaleFactor: 2.5 });
  await ctx.addInitScript(() => {
    const mm = window.matchMedia.bind(window);
    window.matchMedia = (q) => q.includes('display-mode: standalone') ? { matches: true, media: q, addEventListener() {}, removeEventListener() {} } : mm(q);
    try { delete window.PushManager; } catch {}
    addEventListener('DOMContentLoaded', () => { const s = document.createElement('style'); s.textContent = '::-webkit-scrollbar{display:none}'; document.head.appendChild(s); });
    new MutationObserver(() => document.querySelectorAll('input:not([spellcheck])').forEach((i) => i.setAttribute('spellcheck', 'false'))).observe(document, { childList: true, subtree: true });
  });
  const p = await ctx.newPage();
  await p.goto(B + '/login'); await p.fill('#f_email', email); await p.fill('#f_password', 'Demo2026!demo'); await p.click('form button.btn-primary'); await p.waitForLoadState('networkidle');
  return p;
}
const go = async (p, path) => { await p.goto(B + path); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500); };
const shot = async (p, name) => { await p.evaluate(() => document.activeElement?.blur()); await p.waitForTimeout(250); await p.screenshot({ path: `${out}/${name}.png` }); console.log(name); };
const p = await session('proprietaire@demo.jackcie.test');

await go(p, '/dashboard'); await shot(p, 'accueil');
await p.evaluate(() => Alpine.store('assistant').open()); await p.waitForTimeout(800);
await p.locator('#assistant-input').pressSequentially('Cours collectif demain à 16 h avec Léo sur Uni', { delay: 10 });
await p.click('[role=dialog] button[aria-label="Envoyer"]'); await p.waitForSelector('text=C\'est noté', { timeout: 15000 }); await p.waitForTimeout(600);
await p.screenshot({ path: `${out}/assistant.png` }); console.log('assistant');
await p.evaluate(() => Alpine.store('assistant').close?.()); await p.waitForTimeout(1500);

await go(p, '/chevaux/1/sante'); await p.evaluate(() => window.scrollTo(0, 250)); await shot(p, 'sante');
await go(p, '/seances/4/preparer'); await p.evaluate(() => window.scrollTo(0, 120)); await shot(p, 'preparer');
await go(p, '/seances?horse=1&status=planned'); await p.click('main a:has-text("Équilibre au galop")'); await p.waitForLoadState('networkidle');
await p.click('main a:has-text("Bilan")'); await p.waitForLoadState('networkidle');
await p.locator('input[name="added[0][name]"]').fill('Cercles de 20 m'); await p.locator('input[name="added[0][minutes]"]').fill('15');
await p.locator('input[name="added[1][name]"]').fill('Transitions dans l\'allure'); await p.locator('input[name="added[1][minutes]"]').fill('20');
await p.click('label:has(input[name="rider_feeling"][value="4"])'); await p.click('label:has(input[name="horse_behavior"][value="5"])');
await shot(p, 'bilan');
await go(p, '/calendrier'); await shot(p, 'calendrier');
await go(p, '/budget?horse=1'); await p.evaluate(() => window.scrollTo(0, 330)); await shot(p, 'budget');
await go(p, '/chevaux/1/partage'); await p.evaluate(() => window.scrollTo(0, 240)); await shot(p, 'partage');
await go(p, '/chevaux/1/alimentation'); await p.evaluate(() => window.scrollTo(0, 240)); await shot(p, 'alimentation');
await go(p, '/chevaux'); await shot(p, 'chevaux');
const g = await session('gerant@demo.jackcie.test');
await go(g, '/organisation'); await shot(g, 'ecurie');
await b.close();
