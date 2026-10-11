// Captures des vrais écrans (téléphone 390×844, ×3) pour la vidéo des nouveautés.
// Usage : node scripts/nouveautes/capture.mjs <dossier>   (serveur local sur 127.0.0.1:8011, données de démo)
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const OUT = process.argv[2], BASE = 'http://127.0.0.1:8011';
mkdirSync(OUT, { recursive: true });
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctxFor = async (email) => {
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, locale: 'fr-FR', isMobile: true, hasTouch: true });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'Demo2026!demo');
  await p.press('input[name=password]', 'Enter'); await p.waitForLoadState('networkidle');
  return p;
};
const go = async (p, url, theme = 'light') => {
  await p.goto(BASE + url); await p.waitForLoadState('networkidle');
  await p.evaluate((t) => { window.jcSetTheme?.(t); document.querySelectorAll('[data-video-intro], .jc-toast').forEach((e) => e.remove()); }, theme);
  await p.waitForTimeout(400);
};
const shot = async (p, name, sel, offset = 90) => {
  if (sel) await p.evaluate(([s, o]) => { const el = document.querySelector(s); if (el) window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - o); }, [sel, offset]);
  else await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(350);
  await p.screenshot({ path: `${OUT}/${name}.png` });
  console.log(name);
};
const step = async (name, fn) => { try { await fn(); } catch (e) { console.log('ÉCHEC', name, e.message.split('\n')[0]); } };

const anon = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 });
await step('login', async () => { await anon.goto(BASE + '/login'); await anon.waitForTimeout(400); await shot(anon, '10-connexion'); });

const c = await ctxFor('proprietaire@demo.jackcie.test');
await step('dash', async () => { await go(c, '/dashboard'); await shot(c, '01-accueil-clair'); await go(c, '/dashboard', 'dark'); await shot(c, '02-accueil-sombre'); });
await step('apparence', async () => { await go(c, '/parametres', 'dark'); await shot(c, '03-apparence', 'form[action$="/parametres/apparence"]', 140); });
await step('poids', async () => {
  await go(c, '/chevaux/1/poids'); await shot(c, '04-poids', 'h2', 120);
  await c.selectOption('select[name=method]', 'tape', { force: true });
  await c.evaluate(() => document.querySelector('select[name=method]').dispatchEvent(new Event('change', { bubbles: true })));
  await c.fill('input[name=girth_cm]', '190', { force: true }); await c.fill('input[name=length_cm]', '173', { force: true });
  await shot(c, '05-poids-ruban', 'form[action$="/poids"]', 110);
});
await step('rythme', async () => { await go(c, '/chevaux/1/sante'); await shot(c, '06-rythme', '[data-rhythm], form[action$="/rythme-habituel"]', 260); });
await step('facture', async () => {
  await go(c, '/budget'); await shot(c, '07-facture-carte', '#facture', 100);
  await go(c, '/budget/facture/demovideo00000000000000000000000'); await shot(c, '08-facture-lue');
  await shot(c, '09-facture-fusion', '[data-merge]', 100);
});
await step('intervenant', async () => {
  await go(c, '/chevaux/1/soins/nouveau'); await shot(c, '11-soin-form', '[data-new-professional]', 360);
  await c.click('[data-new-professional]'); await c.waitForTimeout(500); await c.screenshot({ path: `${OUT}/12-intervenant-rapide.png` }); console.log('12');
});
await step('select', async () => {
  await go(c, '/chevaux/1/soins/nouveau'); await c.evaluate(() => window.scrollTo(0, 0));
  const t = await c.$('.select-enhance-trigger, [data-select-trigger], button[aria-haspopup=listbox]');
  if (t) { await t.click(); await c.waitForTimeout(400); }
  await c.screenshot({ path: `${OUT}/13-menu-deroulant.png` }); console.log('13', !!t);
});
await step('photo', async () => { await go(c, '/chevaux/1'); const a = await c.$('[data-lightbox]'); if (a) { await a.scrollIntoViewIfNeeded(); await a.click(); await c.waitForTimeout(700); } await c.screenshot({ path: `${OUT}/14-photo.png` }); console.log('14', !!a); });

const g = await ctxFor('gerant@demo.jackcie.test');
await step('pension', async () => {
  await go(g, '/ecurie/pension?mois=2026-09'); await shot(g, '15-pension'); await shot(g, '16-pension-releves', 'form[action$="/pension/releves"]', 160);
  await go(g, '/releves-pension/1'); await shot(g, '17-releve');
});
await step('depense', async () => { await go(c, '/budget'); await c.evaluate(() => document.getElementById('new-expense').showModal()); await c.waitForTimeout(400); await c.screenshot({ path: `${OUT}/18-depense-cheval.png` }); console.log('18'); });
await b.close();
