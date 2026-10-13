// Captures des vrais écrans (téléphone 390×844, ×3) pour la vidéo des nouveautés 2.13.
// Usage : node scripts/nouveautes-2.13/capture.mjs <dossier>
// Serveur local sur 127.0.0.1:8011 lancé avec ANTHROPIC_BASE_URL=http://127.0.0.1:8098 (mock_claude.py) et
// ELEVENLABS_API_KEY=demo (haut-parleur visible) ; données de démonstration uniquement, jamais en production.
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
const OUT = process.argv[2], BASE = 'http://127.0.0.1:8011';
mkdirSync(OUT, { recursive: true });
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, locale: 'fr-FR', isMobile: true, hasTouch: true });
const c = await ctx.newPage();
await c.goto(BASE + '/login'); await c.fill('input[name=email]', 'proprietaire@demo.jackcie.test'); await c.fill('input[name=password]', 'Demo2026!demo');
await c.press('input[name=password]', 'Enter'); await c.waitForLoadState('networkidle');
const clean = () => c.evaluate(() => { window.jcSetTheme?.('light'); document.querySelectorAll('[data-video-intro], .jc-toast, [x-data] > [role=status]').forEach((e) => e.remove()); });
const go = async (url) => { await c.goto(BASE + url); await c.waitForLoadState('networkidle'); await clean(); await c.waitForTimeout(400); };
const shot = async (name, sel, offset = 90) => {
  if (sel) await c.evaluate(([s, o]) => { const el = document.querySelector(s); if (el) window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - o); }, [sel, offset]);
  else await c.evaluate(() => window.scrollTo(0, 0));
  await c.waitForTimeout(350);
  await c.screenshot({ path: `${OUT}/${name}.png` });
  console.log(name);
};
const step = async (name, fn) => { try { await fn(); } catch (e) { console.log('ÉCHEC', name, e.message.split('\n')[0]); } };

await step('accueil', async () => { await go('/dashboard'); await shot('01-accueil'); await shot('02-demarrage', '[data-onboarding]', 80); await shot('03-urgence-proposee', '[data-emergency-cta]', 120); });
await step('assistant', async () => {
  await c.evaluate(() => { try { localStorage.setItem('jc-assistant-speech', '1'); } catch {} });
  await go('/dashboard');
  await c.evaluate(() => Alpine.store('assistant').open()); await c.waitForTimeout(700);
  await c.fill('#assistant-input', 'Combien m\'a coûté le maréchal cette année ?');
  await c.locator('#assistant-input').press('Enter'); await c.waitForTimeout(2500);
  await c.evaluate(() => Alpine.store('assistant').stopCountdown()); await c.waitForTimeout(200);
  await c.screenshot({ path: `${OUT}/04-assistant-question.png` }); console.log('04');
  await c.evaluate(() => Alpine.store('assistant').close());
});
await step('ordonnance', async () => {
  await go('/chevaux/1/sante'); await shot('05-ordonnance-carte', '[data-prescription-card]', 120);
  const [chooser] = await Promise.all([c.waitForEvent('filechooser'), c.click('[data-prescription-card] label')]);
  await chooser.setFiles({ name: 'ordonnance.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', 'base64') });
  await c.waitForURL(/ordonnance\//, { timeout: 15000 }); await c.waitForLoadState('networkidle'); await clean();
  await shot('06-ordonnance-verif'); await shot('07-ordonnance-traitements', '[data-prescribed-treatment]', 160);
});
await step('veto', async () => {
  await go('/chevaux/1/resume-veterinaire'); await shot('08-veto-apercu');
  await shot('09-veto-partage', '[data-vet-share-form]', 100);
  await c.fill('#f_recipient', 'Dr Leroy'); await c.click('[data-vet-share-form] button.btn-primary'); await c.waitForLoadState('networkidle'); await clean();
  const url = await c.evaluate(() => { const b = document.querySelector('[data-vet-share] a[target=_blank]'); return b?.href; });
  writeFileSync(`${OUT}/veto-url.txt`, url || '');
  await shot('10-veto-liens', '[data-vet-share]', 160);
  const pub = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 });
  await pub.goto(url); await pub.waitForLoadState('networkidle'); await pub.waitForTimeout(400);
  await pub.screenshot({ path: `${OUT}/11-veto-public.png` }); console.log('11'); await pub.close();
});
await step('plan', async () => {
  await go('/seances/32/preparer'); await shot('12-plan-carte', '[data-ai-plan]', 140);
  await c.click('[data-ai-plan-ask]'); await c.waitForFunction(() => document.querySelector('[data-ai-plan] dialog')?.open, null, { timeout: 15000 }); await c.waitForTimeout(600);
  await c.screenshot({ path: `${OUT}/13-plan-propose.png` }); console.log('13');
  await c.keyboard.press('Escape');
});
await step('exercices', async () => {
  await go('/seances/32/preparer');
  await c.click('[data-open-picker="main"]'); await c.waitForTimeout(700);
  await c.screenshot({ path: `${OUT}/14-choix-exercices.png` }); console.log('14');
  await go('/exercices'); await shot('15-exercices-pour-vous', '[data-for-you]', 90);
});
await step('bilan', async () => { await go('/seances/32/bilan'); await shot('16-bilan'); await shot('17-bilan-questions', '[data-debrief-questions], form fieldset', 120); });
await step('agenda', async () => {
  await go('/calendrier?vue=liste'); await shot('18-agenda');
  await c.evaluate(() => document.getElementById('calendar-sync')?.showModal()); await c.waitForTimeout(500);
  await c.screenshot({ path: `${OUT}/19-agenda-synchro.png` }); console.log('19');
});
await step('urgence', async () => {
  await go('/chevaux/1/urgence'); await shot('20-urgence-reglage'); await shot('21-urgence-qr', '[data-emergency-print-link]', 300);
  const tok = await c.evaluate(() => [...document.querySelectorAll('a')].find((a) => a.textContent.includes('Voir la fiche publique'))?.href);
  const pub = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 });
  await pub.goto(tok); await pub.waitForLoadState('networkidle'); await pub.waitForTimeout(400);
  await pub.screenshot({ path: `${OUT}/22-urgence-publique.png` }); console.log('22');
  await pub.goto(BASE + '/chevaux/1/urgence/imprimer').catch(() => {}); await pub.close();
  const pr = await ctx.newPage(); await pr.setViewportSize({ width: 390, height: 844 });
  await pr.goto(BASE + '/chevaux/1/urgence/imprimer'); await pr.waitForLoadState('networkidle'); await pr.evaluate(() => document.querySelector('.actions')?.remove());
  await pr.screenshot({ path: `${OUT}/23-urgence-imprimee.png` }); console.log('23'); await pr.close();
});
await step('semaine', async () => { await go('/ma-semaine'); await shot('24-ma-semaine'); });
await step('budget', async () => {
  await go('/budget'); await shot('25-budget', '[data-budget-charts]', 90);
  await c.click('[data-chart-open="month"]'); await c.waitForTimeout(500); await c.screenshot({ path: `${OUT}/26-budget-graphique.png` }); console.log('26');
  await c.keyboard.press('Escape');
  const href = await c.evaluate(() => document.querySelector('[data-expense-link]')?.getAttribute('href'));
  if (href) { await go(new URL(href, BASE).pathname); await shot('27-depense-detail'); }
});
await step('parametres', async () => { await go('/parametres'); await shot('28-parametres'); });
await step('horsligne', async () => {
  await go('/chevaux/1/urgence'); await c.waitForTimeout(1500);
  await go('/dashboard');
  await c.evaluate(() => Alpine.store('assistant').open()); await c.waitForTimeout(700); await c.evaluate(() => Alpine.store('assistant').close());
  await ctx.setOffline(true);
  await c.evaluate(() => { Alpine.store('assistant').status = null; return Alpine.store('assistant').open(); }); await c.waitForTimeout(500);
  await c.fill('#assistant-input', 'Vermifuge donné à Uni ce matin'); await c.locator('#assistant-input').press('Enter'); await c.waitForTimeout(800);
  await c.screenshot({ path: `${OUT}/29-hors-ligne-consigne.png` }); console.log('29');
  await c.goto(BASE + '/agenda').catch(() => {}); await c.waitForTimeout(1200);
  await c.screenshot({ path: `${OUT}/30-hors-ligne-page.png` }); console.log('30');
  await c.evaluate(() => new Promise((r) => { const q = indexedDB.deleteDatabase('jc-queue'); q.onsuccess = q.onerror = q.onblocked = r; }));
  await ctx.setOffline(false);
});
await b.close();
