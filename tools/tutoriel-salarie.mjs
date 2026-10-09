/*
 * Vidéo tutoriel « version salarié » de Centriva, enregistrée depuis l'application réelle (données de démonstration).
 *
 *   [VOIX=durees.json] PWPATH=/chemin/vers/playwright node tools/tutoriel-salarie.mjs <dossier-sortie> <dossier-images> [url] [email] [mot-de-passe]
 *   → la vidéo .webm, Centriva-tutoriel-salarie.srt et Centriva-tutoriel-salarie-voix-off.txt (texte minuté pour la voix off)
 *   puis : ffmpeg -i <video>.webm -vf format=yuv420p -c:v libx264 -crf 22 -movflags +faststart Centriva-tutoriel-salarie.mp4
 *
 * durees.json (facultatif) : {"intro": 3.2, "steps": [8.3, 7.0, …]}, durée de chaque phrase de la voix off, dans l'ordre des sous-titres.
 * Un repère (7 petits carrés en bas à gauche de l'image) indique le numéro du sous-titre affiché : il sert à caler la voix off
 * exactement sur la vidéo (tools/tutoriel-voix.py) et est effacé au montage final.
 * <dossier-images> : images PNG de la caméra simulée (empty.png = étagère, puis une étiquette par article :
 * lingettes.png, gel.png pour la commande, gants.png et masques.png pour la réception), avec un code-barres EAN-13 lisible.
 * Le compte utilisé doit être un salarié avec un panier vide, une livraison « Commandée » (gants S, masques IIR, thermomètre…)
 * à réceptionner et des articles suivis en stock.
 */
import { createRequire } from 'module'; const require = createRequire(import.meta.url); const { chromium } = require(process.env.PWPATH || 'playwright');
import fs from 'fs';
import path from 'path';
import { writeCaptions } from './tutoriel-sous-titres.mjs';
const OUT = process.argv[2], IMG = process.argv[3];
// Durées des phrases de la voix off (secondes), si elle est déjà enregistrée : le rythme de la vidéo suit alors la voix
const VOIX = process.env.VOIX ? JSON.parse(fs.readFileSync(process.env.VOIX, 'utf8')) : null;
const APP = (process.argv[4] || 'http://127.0.0.1:8096/') + 'index.php?r=';
const EMAIL = process.argv[5] || 'claire.secretaire@demo.fr', PASS = process.argv[6] || 'demo1234';
const LOGO = fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../assets/brand/centriva-logo-blanc.svg'), 'utf8');
const W = 1280, H = 720;
const cam = Object.fromEntries(['empty', 'lingettes', 'gel', 'gants', 'masques'].map((n) => [n, 'data:image/png;base64,' + fs.readFileSync(path.join(IMG, n + '.png')).toString('base64')]));
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: W, height: H }, recordVideo: { dir: OUT, size: { width: W, height: H } } });
// Caméra simulée : une étagère, sur laquelle on présente des étiquettes l'une après l'autre (window.__cam('gel'), window.__cam(null))
await ctx.addInitScript((imgs) => {
  const load = (src) => { const i = new Image(); i.src = src; return i; };
  const pics = Object.fromEntries(Object.entries(imgs).map(([k, v]) => [k, load(v)]));
  const st = { cur: null, prev: null, t0: 0 };
  window.__cam = (name) => { st.prev = st.cur; st.cur = name; st.t0 = performance.now(); };
  const fake = async () => {
    const c = document.createElement('canvas'); c.width = 640; c.height = 480;
    const g = c.getContext('2d');
    const ease = (t) => 1 - Math.pow(1 - Math.min(1, Math.max(0, t)), 3);
    const draw = () => {
      const now = performance.now(), k = ease((now - st.t0) / 550);
      g.drawImage(pics.empty, 0, 0, 640, 480);
      const lab = (name, x) => { const im = pics[name]; if (!im) return; const y = 90 + Math.sin(now / 420) * 1.5; g.save(); g.shadowColor = 'rgba(0,0,0,.45)'; g.shadowBlur = 18; g.drawImage(im, x, y, 420, 300); g.restore(); };
      if (st.prev && k < 1) lab(st.prev, 110 - k * 620);
      if (st.cur) lab(st.cur, 110 + (1 - k) * 600);
      requestAnimationFrame(draw);
    };
    draw();
    return c.captureStream(20);
  };
  if (navigator.mediaDevices) navigator.mediaDevices.getUserMedia = fake;
  try { delete window.BarcodeDetector; } catch (e) {}
}, cam);
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
const scannerOpen = () => p.waitForSelector('.scanner video', { timeout: 10000 }).then(() => wait(700));
const showCam = (name) => p.evaluate((n) => window.__cam(n), name);
const t0 = Date.now();

// ================================================================ Introduction (la phrase d'accueil de la voix off se cale sur le repère 51)
await card('Tutoriel salarié', 'Faire une demande d\'articles · Scanner · Suivre sa demande · Réceptionner une livraison<br><span style="font-size:17px;opacity:.75">Centriva — édité par NLapps</span>', VOIX ? Math.max(4200, VOIX.intro * 1000 + 1600) : 6000, 51);

// ================================================================ Partie 1 : se repérer
await part(1, 'Se connecter et se repérer', 'Votre compte, votre centre, le menu');
await p.goto(APP + 'login');
await say(1, 'Se connecter', 'Ouvrez Centriva dans votre navigateur (ordinateur, tablette ou téléphone) et connectez-vous avec votre adresse e-mail professionnelle et votre mot de passe.');
await type('input[name=email]', EMAIL);
await type('input[name=password]', PASS);
await at(0.9); await click('button[type=submit]', { nav: true, after: 300 });
await say(2, 'Le tableau de bord', 'Voici votre page d\'accueil. Elle rassemble les livraisons attendues dans votre centre, vos articles favoris et vos dernières demandes.');
await moveTo(L('.stat, .card').first());
await say(2, 'Le menu', 'Le menu à gauche donne accès à tout : Catalogue pour chercher, Mon panier, Suivi des demandes, Réceptions des livraisons et Inventaire du stock.', { hl: '.sidebar .nav' });
for (const r of ['catalog', 'cart', 'requests', 'receptions', 'stock']) { await moveTo(L(`.sidebar a[href*="r=${r}"]`)); await wait(500); }
await say(2, 'Votre centre', 'Vous travaillez sur plusieurs sites ? Choisissez ici le centre pour lequel vous commandez : le catalogue, le stock et les demandes s\'adaptent.', { hl: '.center-switch' });

// ================================================================ Partie 2 : faire une demande
await part(2, 'Faire une demande d\'articles', 'Rechercher · Scanner deux articles à la suite · Vérifier le panier · Envoyer');
await p.goto(APP + 'dashboard'); await wait(300);
await say(3, 'Rechercher un article', 'Dans la barre de recherche en haut, tapez ce dont vous avez besoin, avec vos mots : « gants M », « de quoi désinfecter »…');
await at(0.3); await type('.topbar input[type=search], header input[name=q]', 'gants nitrile M');
await say(3, 'Suggestions pendant la frappe', 'Les articles correspondants apparaissent dès les premières lettres. Appuyez sur Entrée pour afficher tous les résultats.');
await at(0.75); await p.keyboard.press('Enter'); await p.waitForLoadState();
const prod = p.locator('article.product').first();
await say(3, 'La fiche résumée', 'Chaque carte indique le nom de l\'article, le fournisseur, la référence, le conditionnement (ici une boîte de 100) et le prix négocié hors taxes.', { hl: prod });
await say(3, 'Choisir la quantité', 'Indiquez le nombre de boîtes ou d\'unités voulu, ici 2 boîtes, puis cliquez sur « Ajouter ».');
await setQty(prod.locator('.qty-input'), 2);
await at(0.75); await click(prod.locator('button[type=submit]'));
await say(3, 'Ajouté au panier', 'Un message confirme l\'ajout et le compteur du panier, dans le menu, augmente. Vous pouvez continuer vos recherches : rien n\'est envoyé pour l\'instant.', { hl: '#cart-count' });

await say(4, 'Scanner un code-barres', 'Plus rapide encore : vous avez l\'article sous la main ? Touchez l\'icône code-barres à côté de la recherche, la caméra s\'ouvre.');
await at(0.6); await click('[data-scan="search"]:visible', { after: 0 }); await scannerOpen();
await say(4, 'Viser l\'étiquette', 'Présentez le code-barres de l\'emballage dans le cadre, à 15-20 cm. Inutile d\'appuyer sur un bouton : la lecture est automatique.');
await at(0.35); await Promise.all([p.waitForNavigation({ timeout: 15000 }), showCam('lingettes')]);
await say(4, '1er article : la fiche s\'ouvre', 'Un bip, et la fiche de l\'article scanné s\'ouvre : lingettes désinfectantes, boîte de 100, avec le prix, le fournisseur et le stock de votre centre.');
await moveTo(L('h1'));
const pq = 'form[data-add-cart] .qty-input';
await say(4, 'Quantité puis « Ajouter au panier »', 'Saisissez la quantité (ici 3 boîtes) et ajoutez au panier.');
await setQty(pq, 3); await click('form[data-add-cart] button[type=submit]');
await say(4, '2e article, à la suite', 'On enchaîne avec l\'article suivant : rouvrez le scanner et présentez le deuxième code-barres.');
await click('[data-scan="search"]:visible', { after: 0 }); await scannerOpen();
await at(0.7); await Promise.all([p.waitForNavigation({ timeout: 15000 }), showCam('gel')]);
await say(4, '2e article reconnu', 'Le gel hydroalcoolique 500 ml est reconnu à son tour. Même geste : la quantité, puis « Ajouter au panier ».');
await at(0.5); await setQty(pq, 2); await click('form[data-add-cart] button[type=submit]');
await say(4, 'Code inconnu ?', 'Si un code-barres n\'est pas au catalogue, Centriva vous propose de suggérer l\'article au service achats, avec sa photo.');
await moveTo(L('[data-scan="search"]:visible'));

await say(5, 'Vérifier le panier', 'Ouvrez « Mon panier ». Les articles sont automatiquement classés par fournisseur : vous n\'avez pas à vous en occuper.');
await click('a[href*="r=cart"]', { nav: true });
await moveTo(L('.supplier-block'));
await say(5, 'Stocks bas : suggestions', 'Si des articles suivis en stock sont sous leur seuil, Centriva propose de les ajouter. Décochez ce qui n\'est pas utile, ou ignorez simplement ce cadre.', { hl: 'form[action*="stock/reorder"]' });
await say(5, 'Une ligne par article', 'Pour chaque article : le conditionnement, la quantité modifiable, le prix et la corbeille pour le retirer.', { hl: '.supplier-block' });
await say(5, 'Modifier une quantité', 'Changez le chiffre puis cliquez sur « Mettre à jour les quantités » : le total est recalculé.');
await setQty(p.locator('.supplier-block tr').first().locator('.qty-input'), 3);
await click('button:has-text("Mettre à jour les quantités")', { nav: true });
await say(5, 'Une précision sur un article', 'Le champ sous l\'article permet une précision pour cette ligne : taille, couleur, modèle…');
await type(p.locator('.supplier-block input[name^="comment"]').first(), 'Taille M uniquement');
await say(5, 'Le récapitulatif', 'À droite : le nombre de lignes, le budget de votre centre et le total estimé hors taxes.', { hl: '.card:has(#rc)' });
await say(5, 'Commentaire et urgence', 'Ajoutez si besoin un commentaire pour le service achats. Cochez « Demande urgente » seulement en cas de vraie urgence : elle sera traitée en priorité.');
await type('#rc', 'Pour la salle de soins n°2, avant jeudi');
await moveTo(L('input[name=urgent]')); await hold();
await click('button[name=then][value=submit]', { nav: true, after: 300 });
await say(5, 'Demande envoyée', 'C\'est envoyé ! Le service achats regroupe votre demande avec celles de vos collègues, fournisseur par fournisseur, puis passe la commande.', { hl: '.flash' });

if (!p.url().includes('r=requests')) await click('a[href*="r=requests"]', { nav: true });
await say(6, 'Suivre votre demande', '« Suivi des demandes » liste vos demandes. La nouvelle apparaît en haut, chaque article est « En attente » : le service achats ne l\'a pas encore commandé.', { hl: p.locator('.stack > .card').first() });
await say(6, 'Les étapes d\'un article', 'Le statut de chaque article évolue tout seul : En attente → Commandée → Livraison partielle → Reçue. Vous pouvez aussi annuler une ligne tant qu\'elle est en attente.', { hl: '.chips' });
await say(6, 'Une demande déjà commandée', 'Plus bas, une demande précédente est « Commandée » : le numéro du bon de commande s\'affiche, et un bouton « Réception » permet de valider la livraison quand elle arrive.', { hl: p.locator('.stack > .card:has-text("BC-2026-0021")').first() });
await say(6, 'Les notifications', 'La cloche vous prévient à chaque étape : demande commandée, livraison reçue, article refusé avec son motif. Vous pouvez aussi les recevoir par e-mail.', { hl: '[data-bell]' });

// ================================================================ Partie 3 : réceptionner
await part(3, 'Réceptionner une livraison', 'Scanner les colis · Corriger les quantités · Enregistrer');
await p.goto(APP + 'receptions'); await wait(300);
await say(7, 'Les livraisons attendues', 'Les colis sont arrivés ? Ouvrez « Réceptions » : la liste montre les commandes attendues dans votre centre, avec leur avancement.', { hl: '.card:has-text("Commandes en attente de livraison")' });
const row21 = p.locator('li:has-text("BC-2026-0021")').first();
await say(7, 'Repérer le bon de commande', 'Retrouvez la livraison grâce au fournisseur et au numéro du bon de commande, puis cliquez sur « Réceptionner ».', { hl: row21 });
await at(0.8); await click(row21.locator('a:has-text("Réceptionner")'), { nav: true, after: 300 });
await say(8, 'Le bon de livraison', 'En haut : le fournisseur, le numéro du bon et la barre d\'avancement (0 article reçu sur 12). Dessous, chaque article commandé, avec la personne qui l\'a demandé.', { hl: '.page-head' });
await say(8, 'Une ligne par article', 'À droite de chaque ligne : la quantité reçue sur la quantité commandée. Il y a trois façons de la remplir : scanner, cocher ou saisir.', { hl: '.recv-line' });
await say(8, 'Scanner les colis', 'Le plus simple : « Scanner les articles livrés ». La caméra reste ouverte et chaque code-barres lu compte une boîte sur la bonne ligne.');
await click('[data-recv-scan]', { after: 0 }); await scannerOpen();
await hold(); await showCam('gants'); await wait(1300);
await say(8, '1er code : gants nitrile S', 'Bip : la boîte de gants est comptée. Le message en bas confirme « 1/3 » : 1 boîte reçue sur les 3 commandées.');
await say(8, 'Boîte suivante du même article', 'Retirez la boîte du cadre et présentez la suivante : deuxième boîte de gants, 2/3. Une boîte restée devant la caméra n\'est comptée qu\'une seule fois.');
await showCam(null); await wait(1500); await showCam('gants');
await hold(); await showCam('masques'); await wait(1300);
await say(8, '2e code : masques chirurgicaux', 'On passe à un autre article : le code des masques est reconnu et compté sur sa ligne (1/4). Un article absent du bon est signalé en rouge.');
await say(8, 'Fermer le scanner', 'Quand tous les colis sont scannés, touchez « Fermer ».');
await at(0.4); await click('.scanner [data-close]', { after: 300 });
const lineOf = (txt) => p.locator('.recv-line', { hasText: txt }).first();
await say(9, 'Le résultat du scan', 'Les quantités scannées sont reportées : 2 boîtes de gants S sur 3, 1 boîte de masques sur 4.', { hl: lineOf('Gants d\'examen nitrile non poudrés — taille S') });
await say(9, 'Cocher : reçu en totalité', 'Sans scanner, cochez la case d\'un article reçu en totalité : la quantité se remplit d\'un coup (ici le thermomètre, 1/1).');
await click(lineOf('Thermomètre').locator('.big-check'));
await say(9, 'Saisir la quantité réelle', 'Vous avez compté les boîtes à la main ? Tapez directement la quantité livrée : ici 4 boîtes de masques sur 4, la ligne passe au vert.');
await at(0.3); await setQty(lineOf('Masques').locator('.qty-input'), 4);
await say(9, 'Article manquant', 'Les abaisse-langues ne sont pas dans le colis : laissez 0. Ils restent attendus et pourront être réceptionnés à la prochaine livraison.', { hl: lineOf('Abaisse-langues') });
await say(9, 'Enregistrer la réception', 'Vérifiez une dernière fois puis cliquez sur « Enregistrer la réception ».');
await moveTo(L('#recv-form button[type=submit]')); await hold();
await click('#recv-form button[type=submit]', { nav: true, after: 300 });
await say(10, 'Réception enregistrée', 'La livraison passe en « Livraison partielle » et la barre d\'avancement est à jour. Le stock de votre centre a été augmenté automatiquement des quantités reçues.', { hl: '.page-head' });
await say(10, 'Tout est tracé', 'L\'historique garde la trace de chaque réception : date, heure et personne. Les demandeurs sont prévenus que leurs articles sont arrivés.', { hl: '.timeline' });
await hold(); await p.goto(APP + 'requests'); await wait(300);
await say(10, 'Côté suivi des demandes', 'Dans « Suivi des demandes », la demande affiche désormais « Livraison partielle ». Quand le reste arrivera, refaites une réception : elle passera à « Reçue ».', { hl: p.locator('.stack > .card:has-text("BC-2026-0021")').first() });

// ================================================================ Partie 4 : stock et aide
await part(4, 'Inventaire et aide', 'Compter le stock sur tablette · Poser une question');
await p.goto(APP + 'stock/quick'); await wait(300);
await say(11, 'Inventaire tablette', 'Pour compter le stock, ouvrez Inventaire puis « Inventaire tablette » : scannez un article et saisissez la quantité présente sur l\'étagère.');
await at(0.45); await click('[data-quick-scan]', { after: 0 }); await scannerOpen();
await showCam('gants'); await p.waitForSelector('[data-quick-card]:not(.hidden)', { timeout: 15000 }).catch(async (e) => { await p.screenshot({ path: path.join(OUT, 'erreur.png') }); throw e; });
const qty = p.locator('[data-q-qty]'); const cur = parseInt(await qty.inputValue(), 10) || 0;
await say(11, 'Seulement le stock réel', 'Saisissez seulement ce que vous voyez sur l\'étagère : l\'écart avec le stock théorique est calculé tout seul et enregistré comme entrée ou sortie.');
await showCam(null); await setQty(qty, cur + 1);
await at(0.85); await click('[data-q-go]', { after: 300 });
await say(11, 'Article suivant', 'Après validation, le scanner se relance pour l\'article suivant. Fermez-le quand l\'inventaire est terminé.');
await at(0.7); if (await p.locator('.scanner [data-close]').count()) await click('.scanner [data-close]', { after: 200 });
await hold(); await p.goto(APP + 'dashboard'); await wait(300);
await say(12, 'Besoin d\'aide ?', 'Le bouton « Aide », en bas à droite de chaque page, répond tout de suite aux questions courantes.');
await click('[data-help-open]', { after: 300 });
await type('[data-help-form] input[type=text]', 'comment réceptionner une livraison ?');
await p.keyboard.press('Enter');
await say(12, 'Un conseiller si besoin', 'Pas de réponse ? « Parler à un conseiller » ouvre une conversation directe avec l\'équipe NLapps, aux heures d\'ouverture.');
await hold();
if (curHl) await hl(curHl, false);
await p.evaluate(() => window.__tuto && window.__tuto('', '', '', 0)); cue('end');

// ================================================================ Fin
await card('À vous de jouer !', 'Rechercher ou scanner → Ajouter → Vérifier le panier → Envoyer<br>Suivre la demande → Réceptionner (scanner, cocher ou saisir) → Enregistrer<br><span style="font-size:17px;opacity:.75">Une question ? Bouton « Aide » en bas à droite de l\'écran</span>', 5000);
cue('end');
fs.writeFileSync(path.join(OUT, 'sous-titres.json'), JSON.stringify(cues, null, 1));
writeCaptions(cues, OUT); // .srt et texte de voix off (minutage approximatif)
console.log('étapes', step, '· durée ~', Math.round((Date.now() - t0) / 1000), 's');
const vid = await p.video().path();
await ctx.close(); await b.close();
console.log('VIDEO', vid);
