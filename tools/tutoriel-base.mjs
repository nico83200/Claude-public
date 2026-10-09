/*
 * Socle commun des vidéos tutoriels courtes de Centriva : sous-titres, curseur visible, écrans titres, repère de
 * synchronisation (pour caler la voix off avec tools/tutoriel-voix.py), rythme réglé sur la voix si elle existe.
 *
 *   const t = await tutoriel({ out, base: 'Centriva-tutoriel-xxx' });
 *   await t.card('Titre', 'Sous-titre', 6000, 51);        // écran titre (51 = repère de la phrase d'accueil)
 *   await t.say(1, 'Titre court', 'Phrase du sous-titre…', { hl: '.card' });
 *   …
 *   await t.finish('À vous de jouer !', 'Résumé');
 *
 * Variables d'environnement : PWPATH (Playwright), VOIX (durées des phrases enregistrées, JSON {intro, steps:[…]}).
 */
import { createRequire } from 'module'; const require = createRequire(import.meta.url); const { chromium } = require(process.env.PWPATH || 'playwright');
import fs from 'fs';
import path from 'path';
import { writeCaptions } from './tutoriel-sous-titres.mjs';

export async function tutoriel({ out, base, app = 'http://127.0.0.1:8096/', extraInit = null, extraInitArg = null }) {
  const OUT = out;
  const VOIX = process.env.VOIX ? JSON.parse(fs.readFileSync(process.env.VOIX, 'utf8')) : null;
  const APP = app + 'index.php?r=';
  const LOGO = fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../assets/brand/centriva-logo-blanc.svg'), 'utf8');
  const W = 1280, H = 720;
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: W, height: H }, recordVideo: { dir: OUT, size: { width: W, height: H } } });
  if (extraInit) await ctx.addInitScript(extraInit, extraInitArg);
  // Calque du tutoriel : légende, numéro d'étape, curseur visible, effet de clic (réinjecté à chaque page)
  await ctx.addInitScript(() => {
    const install = () => {
      if (document.getElementById('tuto-cap') || !document.body) return;
      const st = document.createElement('style');
      st.textContent = `#tuto-cap{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:2147483646;max-width:1000px;width:calc(100% - 80px);background:rgba(17,16,46,.92);color:#fff;border-radius:16px;padding:14px 22px 16px;font:500 21px/1.4 Inter,system-ui,sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.35);display:flex;gap:16px;align-items:center;transition:opacity .3s;pointer-events:none}
  #tuto-cap[hidden]{display:none}#tuto-cap b.n{flex:none;display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);font-size:20px}
  #tuto-cap .t{display:block;font-size:14px;letter-spacing:.06em;text-transform:uppercase;color:#c4b5fd;font-weight:700;margin-bottom:2px}
  #tuto-cur{position:fixed;z-index:2147483647;width:26px;height:26px;margin:-4px 0 0 -4px;pointer-events:none;left:-50px;top:-50px;transition:left .05s linear,top .05s linear}
  #tuto-cur svg{filter:drop-shadow(0 2px 3px rgba(0,0,0,.4))}.tuto-ripple{position:fixed;z-index:2147483646;width:46px;height:46px;margin:-23px 0 0 -23px;border-radius:50%;border:3px solid #a855f7;pointer-events:none;animation:tr .55s ease-out forwards}
  @keyframes tr{from{transform:scale(.3);opacity:1}to{transform:scale(1.4);opacity:0}}.tuto-hl{outline:4px solid #f59e0b !important;outline-offset:4px;border-radius:10px;transition:outline .2s}`;
      document.head.appendChild(st);
      const cap = document.createElement('div'); cap.id = 'tuto-cap'; cap.hidden = true; cap.innerHTML = '<b class="n"></b><div><span class="t"></span><span class="x"></span></div>';
      document.body.appendChild(cap);
      const cur = document.createElement('div'); cur.id = 'tuto-cur';
      cur.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24"><path d="M4 2l16 10-7 1.5L9.5 21z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
      document.body.appendChild(cur);
      const mk = document.createElement('div'); mk.id = 'tuto-mk'; mk.style.cssText = 'position:fixed;left:0;bottom:0;z-index:2147483647;display:flex;pointer-events:none';
      for (let i = 0; i < 7; i++) { const q = document.createElement('i'); q.style.cssText = 'display:block;width:12px;height:12px;background:#000'; mk.appendChild(q); }
      document.body.appendChild(mk);
      window.__mark = (v) => { [...mk.children].forEach((q, i) => { q.style.background = v && (i === 0 || (v >> (i - 1)) & 1) ? '#fff' : '#000'; }); mk.style.visibility = v ? 'visible' : 'hidden'; };
      const pos = JSON.parse(sessionStorage.getItem('tuto-pos') || 'null');
      if (pos) { cur.style.left = pos[0] + 'px'; cur.style.top = pos[1] + 'px'; }
      addEventListener('mousemove', (e) => { cur.style.left = e.clientX + 'px'; cur.style.top = e.clientY + 'px'; sessionStorage.setItem('tuto-pos', JSON.stringify([e.clientX, e.clientY])); }, true);
      addEventListener('mousedown', (e) => { const r = document.createElement('div'); r.className = 'tuto-ripple'; r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px'; document.body.appendChild(r); setTimeout(() => r.remove(), 600); }, true);
      window.__tuto = (n, t, x, v) => { const c = document.getElementById('tuto-cap'); window.__mark(v || 0); if (!x) { c.hidden = true; sessionStorage.removeItem('tuto-cap'); return; } c.hidden = false; c.querySelector('.n').textContent = n; c.querySelector('.t').textContent = t; c.querySelector('.x').textContent = x; sessionStorage.setItem('tuto-cap', JSON.stringify([n, t, x, v])); };
      const saved = JSON.parse(sessionStorage.getItem('tuto-cap') || 'null'); if (saved) window.__tuto(...saved);
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', install) : install();
  });
  const p = await ctx.newPage();
  const tStart = Date.now(), cues = []; // minutage approximatif des sous-titres (le calage exact se fait avec le repère dans l'image)
  const cue = (kind, n, t, x) => { const at = (Date.now() - tStart) / 1000; const last = cues[cues.length - 1]; if (last && last.end == null) last.end = at; if (x) cues.push({ kind, n, title: t, text: x, start: at, end: null }); };
  p.on('pageerror', e => console.log('PAGEERR', e.message)); p.on('dialog', d => d.accept());
  const wait = (ms) => p.waitForTimeout(ms);
  const L = (sel) => typeof sel === 'string' ? p.locator(sel).first() : sel;
  const hl = async (sel, on = true) => on
    ? L(sel).evaluate((el) => el.classList.add('tuto-hl'), null, { timeout: 3000 }).catch(() => {})
    : p.evaluate(() => document.querySelectorAll('.tuto-hl').forEach((el) => el.classList.remove('tuto-hl'))).catch(() => {}); // la page a pu changer entre-temps

  // Rythme : chaque sous-titre reste affiché le temps de sa phrase (voix off) + une respiration ; les gestes à l'écran
  // se font pendant que la phrase est dite. Sans voix off : temps de lecture (2 s + 62 ms par caractère).
  const GAP = 450;
  let step = 0, curStart = 0, curDur = 0, curHl = null;
  const durOf = (x) => VOIX ? Math.round(VOIX.steps[step] * 1000) + GAP : Math.max(5000, 2000 + x.length * 62);
  const hold = async () => { const left = curStart + curDur - Date.now(); if (left > 0) await wait(left); };
  const at = async (f) => { const left = curStart + curDur * f - Date.now(); if (left > 0) await wait(left); };
  const say = async (n, t, x, opts = {}) => {
    await hold();
    if (curHl) { await hl(curHl, false); curHl = null; }
    if (opts.hl) { await hl(opts.hl); curHl = opts.hl; }
    curDur = durOf(x); curStart = Date.now(); step++;
    cue('step', n, t, x);
    await p.evaluate(([n, t, x, v]) => window.__tuto && window.__tuto(n, t, x, v), [String(n), t, x, step]);
    if (opts.hl) await moveTo(L(opts.hl)).catch(() => {});
  };
  const moveTo = async (loc) => {
    await loc.scrollIntoViewIfNeeded({ timeout: 3000 }).catch(() => {}); const bb = await loc.boundingBox({ timeout: 3000 }).catch(() => null); if (!bb) return;
    await p.mouse.move(bb.x + Math.min(bb.width / 2, 60), bb.y + bb.height / 2, { steps: 24 }); await wait(250);
  };
  const click = async (sel, opts = {}) => { const loc = L(sel); await moveTo(loc); await wait(200); if (opts.nav) { await Promise.all([p.waitForNavigation(), loc.click()]); } else { await loc.click(); } await wait(opts.after ?? 500); };
  const type = async (sel, text) => { const loc = L(sel); await moveTo(loc); await loc.click(); await loc.pressSequentially(text, { delay: 65 }); await wait(250); };
  const setQty = async (sel, v) => { const loc = L(sel); await moveTo(loc); await loc.click({ clickCount: 3 }); await loc.pressSequentially(String(v), { delay: 110 }); await loc.dispatchEvent('input'); await wait(300); };
  const markHtml = (v) => `<div style="position:fixed;left:0;bottom:0;display:flex">${[...Array(7)].map((_, i) => `<i style="display:block;width:12px;height:12px;background:${v && (i === 0 || (v >> (i - 1)) & 1) ? '#fff' : '#000'}"></i>`).join('')}</div>`;
  const card = async (title, sub, ms, mark = 0) => {
    await hold(); curDur = 0;
    cue('card', '', title.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim(), sub.replace(/<br>/g, ' — ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim());
    await p.setContent(`<html><head><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@500;800&display=swap"></head><body style="margin:0;width:${W}px;height:${H}px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:26px;background:linear-gradient(135deg,#1e1b4b,#4c1d95 60%,#9d174d);font-family:Inter,system-ui,sans-serif;color:#fff;text-align:center;overflow:hidden">
      <div style="width:320px;height:90px">${LOGO.replace('<svg', '<svg width="100%" height="100%" preserveAspectRatio="xMidYMid meet"')}</div><div style="font-size:52px;font-weight:800;letter-spacing:-.02em">${title}</div><div style="font-size:23px;opacity:.88;line-height:1.55;max-width:960px">${sub}</div>${markHtml(mark)}</body></html>`);
    await wait(300); await wait(ms);
  };
  const part = (k, title, sub) => card(`<span style="display:block;font-size:22px;letter-spacing:.14em;text-transform:uppercase;color:#c4b5fd;margin-bottom:10px">Partie ${k}</span>${title}`, sub, 2600);

  const login = async (email, pass = 'demo1234') => {
    await p.goto(APP + 'logout').catch(() => {});
    await p.goto(APP + 'login');
    await p.fill('input[name=email]', email); await p.fill('input[name=password]', pass);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  };
  const t0 = Date.now();
  const finish = async (title, sub) => {
    await hold();
    if (curHl) await hl(curHl, false);
    await p.evaluate(() => window.__tuto && window.__tuto('', '', '', 0)); cue('end');
    await card(title, sub + '<br><span style="font-size:17px;opacity:.75">Une question ? Bouton « Aide » en bas à droite de l\'écran</span>', 5000);
    cue('end');
    fs.writeFileSync(path.join(OUT, 'sous-titres.json'), JSON.stringify(cues, null, 1));
    writeCaptions(cues, OUT, base);
    console.log('étapes', step, '· durée ~', Math.round((Date.now() - t0) / 1000), 's');
    const vid = await p.video().path();
    await ctx.close(); await b.close();
    console.log('VIDEO', vid);
  };
  return { p, APP, VOIX, wait, L, hl, say, hold, at, moveTo, click, type, setQty, card, part, login, finish, cue };
}
