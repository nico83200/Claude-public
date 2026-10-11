// Tournage du tutoriel mobile : chaque scène dure le temps de lire sa voix off.
import { chromium, devices } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
const [,, outDir, profile] = process.argv;
const B = 'http://127.0.0.1:8011';
const scenes = JSON.parse(readFileSync(new URL('./scenes.json', import.meta.url)));
const WPS = 2.5; // mots par seconde de voix off
const voiceSeconds = (t) => t.split(/\s+/).filter(Boolean).length / WPS;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const ctx = await chromium.launchPersistentContext(profile, {
  executablePath: '/opt/pw-browsers/chromium', ...devices['Pixel 7'], viewport: { width: 390, height: 844 }, deviceScaleFactor: 2,
  args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
});
const t0 = 0; // repères en temps absolu (secondes), comme les images capturées
await ctx.grantPermissions(['notifications', 'microphone'], { origin: B });
await ctx.addInitScript(() => {
  const mm = window.matchMedia.bind(window);
  window.matchMedia = (q) => q.includes('display-mode: standalone') ? { matches: true, media: q, addEventListener() {}, removeEventListener() {} } : mm(q);
  // Indicateur de toucher (cercle) et pas de barres de défilement
  addEventListener('DOMContentLoaded', () => {
    const st = document.createElement('style');
    st.textContent = `::-webkit-scrollbar{display:none} .tuto-touch{position:fixed;z-index:2147483647;width:46px;height:46px;margin:-23px 0 0 -23px;border-radius:50%;background:rgba(13,49,34,.28);border:2px solid rgba(255,255,255,.9);box-shadow:0 2px 12px rgba(0,0,0,.25);pointer-events:none;animation:tuto-t .6s ease-out forwards}@keyframes tuto-t{0%{transform:scale(.4);opacity:1}100%{transform:scale(1.25);opacity:0}}`;
    document.head.appendChild(st);
  });
  // Pas de soulignement du correcteur orthographique dans la vidéo
  new MutationObserver(() => document.querySelectorAll('input:not([spellcheck]), textarea:not([spellcheck])').forEach((i) => i.setAttribute('spellcheck', 'false')))
    .observe(document, { childList: true, subtree: true });
  addEventListener('pointerdown', (e) => {
    const d = document.createElement('div'); d.className = 'tuto-touch'; d.style.left = e.clientX + 'px'; d.style.top = e.clientY + 'px';
    document.documentElement.appendChild(d); setTimeout(() => d.remove(), 700);
  }, true);
});
const p = ctx.pages()[0] ?? await ctx.newPage();
await p.goto(B + '/login');
if (await p.locator('#f_email').count()) {
  await p.fill('#f_email', 'proprietaire@demo.jackcie.test'); await p.fill('#f_password', 'Demo2026!demo');
  await p.click('form button.btn-primary'); await p.waitForLoadState('networkidle');
}
await p.waitForTimeout(1200);

// Capture des images de l'écran (Chromium les envoie à chaque changement d'affichage)
mkdirSync(outDir + '/frames', { recursive: true });
const frames = [];
const cdp = await ctx.newCDPSession(p);
cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
  const file = outDir + '/frames/' + String(frames.length).padStart(6, '0') + '.jpg';
  writeFileSync(file, Buffer.from(data, 'base64'));
  frames.push({ file, t: metadata.timestamp });
  cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
});
await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 88, maxWidth: 780, maxHeight: 1688, everyNthFrame: 1 });
await sleep(300);

const card = async (title, subtitle) => p.evaluate(([title, subtitle]) => {
  const el = document.createElement('div'); el.id = 'tuto-card';
  el.innerHTML = `<img src="/icons/icon-192.png" style="width:96px;height:96px;border-radius:22%;box-shadow:0 10px 30px rgba(0,0,0,.25)"><h1 style="margin-top:28px;color:#fff;font:700 30px Inter Variable,system-ui;letter-spacing:-.02em;text-align:center">${title}</h1><p style="margin-top:10px;color:rgba(255,255,255,.75);font:400 17px Inter Variable,system-ui;text-align:center;max-width:300px">${subtitle}</p>`;
  Object.assign(el.style, { position: 'fixed', inset: '0', zIndex: 2147483646, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: '#0d3122', transition: 'opacity .6s' });
  document.body.appendChild(el);
}, [title, subtitle]);
const uncard = async () => { await p.evaluate(() => { const c = document.getElementById('tuto-card'); if (c) { c.style.opacity = '0'; setTimeout(() => c.remove(), 600); } }); await sleep(600); };
const tap = async (selector) => { const l = p.locator(selector).first(); await l.scrollIntoViewIfNeeded(); await sleep(250); await l.tap(); };
const smoothScroll = async (y, ms = 1200) => { await p.evaluate(([y]) => window.scrollTo({ top: y, behavior: 'smooth' }), [y]); await sleep(ms); };
const go = async (path) => { await p.goto(B + path); await p.waitForLoadState('networkidle'); await sleep(400); };

const marks = [];
const actions = {
  intro: async () => { await card('Bienvenue dans Jack&amp;Cie', 'Toute la vie de votre cheval, dans votre poche.'); await sleep(500); },
  notifs: async () => { await uncard(); await sleep(voiceSeconds(scenes[1].text) * 1000 - 2500); await tap('#push-gate button:has-text("Activer les notifications")'); await sleep(1500); },
  accueil: async () => { await sleep(2500); await smoothScroll(420, 2500); await smoothScroll(900, 2500); await smoothScroll(0, 1000); },
  assistant: async () => {
    await tap('nav[aria-label="Navigation rapide"] button[aria-label="Parler à l\'assistant"]'); await sleep(3500);
    const cancel = p.locator('[role=dialog] button:has-text("Annuler")').first();
    if (await cancel.isVisible()) await cancel.tap();
    await sleep(600);
    await tap('#assistant-input');
    await p.locator('#assistant-input').pressSequentially('Cours collectif demain à 16 h avec Jimmy sur Uni', { delay: 55 });
    await sleep(400);
  },
  assistant2: async () => {
    await tap('[role=dialog] button[aria-label="Envoyer"]');
    await p.waitForSelector('text=C\'est noté', { timeout: 15000 }); await sleep(800);
  },
  cheval: async () => {
    await p.evaluate(() => Alpine.store('assistant').close?.()); await sleep(500);
    await tap('nav[aria-label="Navigation rapide"] a:has-text("Chevaux")'); await p.waitForLoadState('networkidle'); await sleep(1500);
    await tap('main a:has-text("Uni")'); await p.waitForLoadState('networkidle'); await sleep(2500);
    await p.locator('nav[aria-label="Sections du dossier"]').evaluate((n) => n.scrollTo({ left: 600, behavior: 'smooth' })); await sleep(1800);
    await p.locator('nav[aria-label="Sections du dossier"]').evaluate((n) => n.scrollTo({ left: 0, behavior: 'smooth' })); await sleep(1000);
  },
  sante: async () => { await tap('nav[aria-label="Sections du dossier"] a:has-text("Santé")'); await p.waitForLoadState('networkidle'); await sleep(2500); await smoothScroll(500, 2500); },
  bilan: async () => {
    await go('/seances?horse=1&status=planned'); await sleep(1200);
    await tap('main a:has-text("Équilibre au galop")'); await p.waitForLoadState('networkidle'); await sleep(1200);
    await tap('main a:has-text("Bilan")'); await p.waitForLoadState('networkidle'); await sleep(1000);
    await tap('input[name="added[0][name]"]'); await p.locator('input[name="added[0][name]"]').pressSequentially('Cercles de 20 m', { delay: 50 });
    await p.locator('input[name="added[0][minutes]"]').fill('15'); await sleep(600);
    await p.evaluate(() => document.activeElement?.blur()); await sleep(300);
    await smoothScroll(380, 1200);
    await tap('label:has(input[name="rider_feeling"][value="4"])'); await sleep(500);
  },
  calendrier: async () => { await p.evaluate(() => document.activeElement?.blur()); await sleep(200); await tap('nav[aria-label="Navigation rapide"] a:has-text("Agenda")'); await p.waitForLoadState('networkidle'); await sleep(2000); await smoothScroll(300, 1500); },
  budget: async () => {
    await go('/budget'); await sleep(2500);
    await tap('main button:has-text("Payé")'); await p.waitForLoadState('networkidle'); await sleep(1500);
    await smoothScroll(1100, 2500);
  },
  menu: async () => { await smoothScroll(0, 600); await tap('nav[aria-label="Navigation rapide"] button:has-text("Menu")'); await sleep(2500); },
  outro: async () => { await card('C\'est à vous !', 'Bonne route avec Jack&amp;Cie.'); await sleep(500); },
};
for (const s of scenes) {
  const start = Date.now();
  marks.push({ id: s.id, start: start / 1000 });
  try { await actions[s.id](); } catch (e) { console.log('ÉCHEC scène', s.id, String(e.message).split('\n')[0]); await p.screenshot({ path: outDir + '/echec-' + s.id + '.png' }); await ctx.close(); process.exit(1); }
  const target = voiceSeconds(s.text) * 1000 + 900;
  const left = target - (Date.now() - start);
  if (left > 0) await sleep(left);
  marks[marks.length - 1].end = Date.now() / 1000;
  console.log(s.id, (marks.at(-1).end - marks.at(-1).start).toFixed(1), 's, voix', voiceSeconds(s.text).toFixed(1));
}
await sleep(800);
await cdp.send('Page.stopScreencast').catch(() => {});
await ctx.close();
writeFileSync(outDir + '/marks.json', JSON.stringify({ frames, marks }, null, 1));
console.log('images :', frames.length);
