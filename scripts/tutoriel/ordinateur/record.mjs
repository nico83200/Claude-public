// Tournage du tutoriel version ordinateur : chaque scène dure le temps de son paragraphe de voix off.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
const [,, outDir, profile, dureesFile] = process.argv;
const B = 'http://127.0.0.1:8011';
const D = JSON.parse(readFileSync(dureesFile)).durations;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const ctx = await chromium.launchPersistentContext(profile, {
  executablePath: '/opt/pw-browsers/chromium', viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1.5,
  args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
});
await ctx.addInitScript(() => {
  addEventListener('DOMContentLoaded', () => {
    const st = document.createElement('style');
    st.textContent = `::-webkit-scrollbar{display:none} *{scrollbar-width:none}
      #tuto-cursor{position:fixed;z-index:2147483647;left:0;top:0;width:22px;height:22px;pointer-events:none;transform:translate(-3px,-2px);filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))}
      .tuto-click{position:fixed;z-index:2147483646;width:40px;height:40px;margin:-20px 0 0 -20px;border-radius:50%;background:rgba(13,49,34,.22);border:2px solid rgba(13,49,34,.55);pointer-events:none;animation:tuto-c .5s ease-out forwards}
      @keyframes tuto-c{0%{transform:scale(.3);opacity:1}100%{transform:scale(1.2);opacity:0}}`;
    document.head.appendChild(st);
    const c = document.createElement('div'); c.id = 'tuto-cursor';
    c.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 2l7.5 19 2.6-7.4L21 11z" fill="#fff" stroke="#141915" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    document.documentElement.appendChild(c);
    const pos = JSON.parse(sessionStorage.getItem('tuto-pos') || '[640,360]'); c.style.left = pos[0] + 'px'; c.style.top = pos[1] + 'px';
  });
  addEventListener('mousemove', (e) => { const c = document.getElementById('tuto-cursor'); if (c) { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; } sessionStorage.setItem('tuto-pos', JSON.stringify([e.clientX, e.clientY])); }, true);
  addEventListener('mousedown', (e) => { const d = document.createElement('div'); d.className = 'tuto-click'; d.style.left = e.clientX + 'px'; d.style.top = e.clientY + 'px'; document.documentElement.appendChild(d); setTimeout(() => d.remove(), 600); }, true);
  new MutationObserver(() => document.querySelectorAll('input:not([spellcheck]), textarea:not([spellcheck])').forEach((i) => i.setAttribute('spellcheck', 'false'))).observe(document, { childList: true, subtree: true });
});
const p = ctx.pages()[0] ?? await ctx.newPage();
let mouse = [640, 360];
const moveTo = async (x, y, steps = 22) => { await p.mouse.move(x, y, { steps }); mouse = [x, y]; };
const click = async (selector, opts = {}) => {
  const l = p.locator(selector).first(); await l.scrollIntoViewIfNeeded(); const bb = await l.boundingBox();
  await moveTo(bb.x + bb.width / 2, bb.y + bb.height / 2, opts.steps ?? 22); await sleep(120); await p.mouse.down(); await sleep(60); await p.mouse.up();
};
const hover = async (selector) => { const bb = await p.locator(selector).first().boundingBox(); await moveTo(bb.x + bb.width / 2, bb.y + bb.height / 2); };
const smoothScroll = async (y, ms = 1200) => { await p.evaluate(([y]) => window.scrollTo({ top: y, behavior: 'smooth' }), [y]); await sleep(ms); };
const settle = async () => { await p.waitForLoadState('networkidle'); await sleep(300); await p.mouse.move(mouse[0], mouse[1]); };

await p.goto(B + '/login');
if (await p.locator('#f_email').count()) { await p.fill('#f_email', 'proprietaire@demo.jackcie.test'); await p.fill('#f_password', 'Demo2026!demo'); await p.click('form button.btn-primary'); await p.waitForLoadState('networkidle'); }
await p.goto(B + '/dashboard'); await settle(); await sleep(800);

mkdirSync(outDir + '/frames', { recursive: true });
const frames = [];
const cdp = await ctx.newCDPSession(p);
cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
  const file = outDir + '/frames/' + String(frames.length).padStart(6, '0') + '.jpg';
  writeFileSync(file, Buffer.from(data, 'base64')); frames.push({ file, t: metadata.timestamp });
  cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
});
await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: 1920, maxHeight: 1080, everyNthFrame: 1 });
await sleep(300);

const card = async (title, subtitle) => p.evaluate(([title, subtitle]) => {
  const el = document.createElement('div'); el.id = 'tuto-card';
  el.innerHTML = `<img src="/icons/icon-192.png" style="width:120px;height:120px;border-radius:22%;box-shadow:0 10px 30px rgba(0,0,0,.25)"><h1 style="margin-top:30px;color:#fff;font:700 54px Inter,system-ui;letter-spacing:-.02em;text-align:center">${title}</h1><p style="margin-top:12px;color:rgba(255,255,255,.75);font:400 22px Inter,system-ui;text-align:center">${subtitle}</p>`;
  Object.assign(el.style, { position: 'fixed', inset: '0', zIndex: 2147483645, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: '#0d3122', transition: 'opacity .6s' });
  document.body.appendChild(el);
}, [title, subtitle]);
const uncard = async () => { await p.evaluate(() => { const c = document.getElementById('tuto-card'); if (c) { c.style.opacity = '0'; setTimeout(() => c.remove(), 600); } }); await sleep(600); };
const nav = (label) => `aside a:has-text("${label}")`;

const actions = {
  intro: async () => { await card('Bienvenue dans Jack&amp;Cie', 'Toute la vie de votre cheval, réunie.'); },
  notifs: async () => {
    await uncard(); await sleep(900);
    await click('aside a:has-text("Paramètres")'); await settle(); await sleep(700);
    await click('main nav a:has-text("Notifications")'); await settle(); await sleep(1300);
    await ctx.grantPermissions(['notifications', 'microphone'], { origin: B });
    await click('button:has-text("Activer les notifications")'); await sleep(2200);
    await hover('label:has-text("Après une séance ou un cours")');
  },
  accueil: async () => { await click(nav('Accueil')); await settle(); await sleep(1500); await moveTo(760, 420); await smoothScroll(380, 2200); await smoothScroll(0, 1200); },
  assistant: async () => {
    await click('header button:has-text("Parler")'); await sleep(1300);
    await click('[role=dialog] button[aria-label="Parler"]'); await sleep(2600);
    const cancel = p.locator('[role=dialog] button:has-text("Annuler")').first(); if (await cancel.isVisible()) await click('[role=dialog] button:has-text("Annuler")');
    await sleep(400); await click('#assistant-input');
    await p.locator('#assistant-input').pressSequentially('Cours collectif demain à 16 h avec Jimmy sur Uni', { delay: 50 });
  },
  assistant2: async () => { await click('[role=dialog] button[aria-label="Envoyer"]'); await p.waitForSelector('text=C\'est noté', { timeout: 15000 }); },
  cheval: async () => {
    await p.evaluate(() => Alpine.store('assistant').close?.()); await sleep(500);
    await click(nav('Chevaux')); await settle(); await sleep(1500);
    await click('main a:has-text("Uni")'); await settle(); await sleep(2500);
    await hover('main a.btn-primary:has-text("Séance")'); await sleep(900); await hover('main a.btn-secondary:has-text("Soin")'); await sleep(900);
    await hover('nav[aria-label="Sections du dossier"] a:has-text("Documents")'); await sleep(1000);
  },
  sante: async () => { await click('nav[aria-label="Sections du dossier"] a:has-text("Santé")'); await settle(); await sleep(2000); await moveTo(700, 500); await smoothScroll(300, 1800); },
  bilan: async () => {
    await p.goto(B + '/seances?horse=1&status=planned'); await settle(); await sleep(600);
    await click('main a:has-text("Équilibre au galop")'); await settle(); await sleep(700);
    await click('main a:has-text("Bilan")'); await settle(); await sleep(500);
    await click('input[name="added[0][name]"]'); await p.locator('input[name="added[0][name]"]').pressSequentially('Cercles de 20 m', { delay: 45 });
    await p.locator('input[name="added[0][minutes]"]').fill('15'); await p.evaluate(() => document.activeElement?.blur());
    await click('label:has(input[name="rider_feeling"][value="4"])');
  },
  calendrier: async () => { await click(nav('Agenda')); await settle(); await moveTo(800, 450); },
  budget: async () => {
    await click(nav('Budget')); await settle(); await sleep(2200);
    await click('main button:has-text("Payé")'); await settle(); await sleep(1200);
    await moveTo(800, 500); await smoothScroll(700, 2200);
  },
  menu: async () => {
    await smoothScroll(0, 500);
    await hover(nav('Partages')); await sleep(1100); await hover('aside a:has-text("Paramètres")'); await sleep(1100);
    await click('aside a:has-text("Tutoriels")'); await settle();
  },
  outro: async () => { await card('C\'est à vous !', 'Bonne route avec Jack&amp;Cie.'); },
};
const marks = [];
for (const id of Object.keys(D)) {
  const start = Date.now(); marks.push({ id, start: start / 1000 });
  try { await actions[id](); } catch (e) { console.log('ÉCHEC scène', id, String(e.message).split('\n')[0]); await p.screenshot({ path: outDir + '/echec-' + id + '.png' }); await ctx.close(); process.exit(1); }
  const left = D[id] * 1000 - (Date.now() - start); if (left > 0) await sleep(left);
  marks.at(-1).end = Date.now() / 1000;
  console.log(id, (marks.at(-1).end - marks.at(-1).start).toFixed(1), 's / cible', D[id]);
}
await sleep(500);
await cdp.send('Page.stopScreencast').catch(() => {});
await ctx.close();
writeFileSync(outDir + '/marks.json', JSON.stringify({ frames, marks }, null, 1));
console.log('images :', frames.length);
