/*
 * Vidéo tutoriel « version salarié » d'Approvia, enregistrée depuis l'application réelle (données de démonstration).
 *
 *   PWPATH=/chemin/vers/playwright node tools/tutoriel-salarie.mjs <dossier-sortie> <camera.y4m> [url] [email] [mot-de-passe]
 *   puis : ffmpeg -i <video>.webm -vf format=yuv420p -c:v libx264 -crf 22 -movflags +faststart Approvia-tutoriel-salarie.mp4
 *
 * <camera.y4m> : vidéo simulant la caméra (un code-barres d'article du catalogue), pour les étapes de scan.
 * Le compte utilisé doit être un salarié avec un panier vide, une livraison « commandée » à réceptionner et des articles suivis en stock.
 */
import { createRequire } from 'module'; const require = createRequire(import.meta.url); const { chromium } = require(process.env.PWPATH || 'playwright');
import fs from 'fs';
import path from 'path';
const OUT = process.argv[2], Y4M = process.argv[3];
const APP = (process.argv[4] || 'http://127.0.0.1:8096/') + 'index.php?r=';
const EMAIL = process.argv[5] || 'claire.secretaire@demo.fr', PASS = process.argv[6] || 'demo1234';
const LOGO = fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../assets/brand/approvia-logo-blanc.svg'), 'utf8');
const W = 1280, H = 720;
const b = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--use-file-for-fake-video-capture=' + Y4M] });
const ctx = await b.newContext({ viewport: { width: W, height: H }, permissions: ['camera'], recordVideo: { dir: OUT, size: { width: W, height: H } } });
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
    const pos = JSON.parse(sessionStorage.getItem('tuto-pos') || 'null');
    if (pos) { cur.style.left = pos[0] + 'px'; cur.style.top = pos[1] + 'px'; }
    addEventListener('mousemove', (e) => { cur.style.left = e.clientX + 'px'; cur.style.top = e.clientY + 'px'; sessionStorage.setItem('tuto-pos', JSON.stringify([e.clientX, e.clientY])); }, true);
    addEventListener('mousedown', (e) => { const r = document.createElement('div'); r.className = 'tuto-ripple'; r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px'; document.body.appendChild(r); setTimeout(() => r.remove(), 600); }, true);
    window.__tuto = (n, t, x) => { const c = document.getElementById('tuto-cap'); if (!x) { c.hidden = true; sessionStorage.removeItem('tuto-cap'); return; } c.hidden = false; c.querySelector('.n').textContent = n; c.querySelector('.t').textContent = t; c.querySelector('.x').textContent = x; sessionStorage.setItem('tuto-cap', JSON.stringify([n, t, x])); };
    const saved = JSON.parse(sessionStorage.getItem('tuto-cap') || 'null'); if (saved) window.__tuto(...saved);
  };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', install) : install();
});
const p = await ctx.newPage();
p.on('pageerror', e => console.log('PAGEERR', e.message)); p.on('dialog', d => d.accept());
const wait = (ms) => p.waitForTimeout(ms);
let mx = W / 2, my = H / 2;
const say = async (n, t, x, ms = 3500) => { await p.evaluate(([n, t, x]) => window.__tuto && window.__tuto(n, t, x), [String(n), t, x]); if (ms) await wait(ms); };
const moveTo = async (loc) => {
  await loc.scrollIntoViewIfNeeded(); const bb = await loc.boundingBox(); if (!bb) return;
  const tx = bb.x + Math.min(bb.width / 2, 60), ty = bb.y + bb.height / 2;
  await p.mouse.move(tx, ty, { steps: 22 }); mx = tx; my = ty; await wait(250);
};
const click = async (sel, opts = {}) => { const loc = typeof sel === 'string' ? p.locator(sel).first() : sel; await moveTo(loc); if (opts.nav) { await Promise.all([p.waitForNavigation(), loc.click()]); } else { await loc.click(); } await wait(opts.after ?? 600); };
const type = async (sel, text) => { const loc = p.locator(sel).first(); await moveTo(loc); await loc.click(); await loc.pressSequentially(text, { delay: 70 }); await wait(300); };
const hl = async (sel, on = true) => p.locator(sel).first().evaluate((el, on) => el.classList.toggle('tuto-hl', on), on).catch(() => {});
const card = async (title, sub, ms) => {
  await p.setContent(`<html><head><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@500;800&display=swap"></head><body style="margin:0;width:${W}px;height:${H}px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:26px;background:linear-gradient(135deg,#1e1b4b,#4c1d95 60%,#9d174d);font-family:Inter,system-ui,sans-serif;color:#fff;text-align:center;overflow:hidden">
    <div style="width:320px;height:90px">${LOGO.replace('<svg', '<svg width="100%" height="100%" preserveAspectRatio="xMidYMid meet"')}</div><div style="font-size:52px;font-weight:800;letter-spacing:-.02em">${title}</div><div style="font-size:23px;opacity:.88;line-height:1.55;max-width:900px">${sub}</div></body></html>`);
  await p.waitForTimeout(500);
  await wait(ms);
};
const t0 = Date.now();

// ---------------------------------------------------------------- Intro
await card('Tutoriel salarié', 'Commander · Scanner · Suivre · Réceptionner · Compter le stock<br><span style="font-size:17px;opacity:.75">Approvia — édité par NLapps</span>', 5000);

// 1. Connexion
await p.goto(APP + 'login');
await say(1, 'Se connecter', 'Ouvrez Approvia et connectez-vous avec votre adresse e-mail professionnelle et votre mot de passe.', 1500);
await type('input[name=email]', EMAIL);
await type('input[name=password]', PASS);
await click('button[type=submit]', { nav: true, after: 400 });

// 2. Tableau de bord, centre
await say(2, 'Votre tableau de bord', 'L\'accueil rassemble vos livraisons attendues, vos favoris et les dernières demandes de votre centre.', 4500);
await hl('.center-switch'); await moveTo(p.locator('.center-switch'));
await say(2, 'Votre centre', 'Vous travaillez sur plusieurs sites ? Choisissez le centre ici : tout l\'écran s\'adapte (catalogue, stock, demandes).', 4500);
await hl('.center-switch', false);

// 3. Recherche
await say(3, 'Trouver un article', 'Tapez simplement ce que vous cherchez, même approximativement : « gants M », « de quoi désinfecter »…', 1200);
await type('.topbar input[type=search], header input[name=q]', 'gants nitrile M');
await wait(1800);
await say(3, 'Suggestions immédiates', 'Les articles correspondants s\'affichent pendant la frappe. Validez pour voir tous les résultats.', 2500);
await p.keyboard.press('Enter'); await p.waitForLoadState(); await wait(1200);
await say(4, 'Ajouter au panier', 'Indiquez la quantité puis « Ajouter ». Le panier se remplit sans quitter la page.', 1500);
const addBtn = p.locator('form[data-add-cart] button[type=submit]').first();
await click(addBtn, { after: 1800 });

// 5. Scanner un code-barres
await say(5, 'Scanner un code-barres', 'Encore plus rapide : touchez l\'icône code-barres et visez l\'étiquette de l\'article avec la caméra.', 1500);
await click('[data-scan="search"]:visible', { after: 3500 });
await p.waitForLoadState(); await wait(800);
await say(5, 'Fiche de l\'article', 'L\'article s\'ouvre directement : prix, conditionnement, emplacement dans votre réserve, stock du centre.', 4500);
const pAdd = p.locator('form[data-add-cart] button[type=submit]').first();
if (await pAdd.count()) await click(pAdd, { after: 1500 });

// 6. Panier et envoi
await say(6, 'Envoyer la demande', 'Ouvrez « Mon panier » pour vérifier les quantités et ajouter une précision si besoin.', 1200);
await click('a[href*="r=cart"]', { nav: true, after: 1200 });
await say(6, 'Commentaire et urgence', 'Ajoutez un commentaire pour le service achats et cochez « urgente » si nécessaire, puis envoyez.', 1200);
await type('#rc', 'Pour la salle de soins n°2');
await wait(600);
await click('button[name=then][value=submit]', { nav: true, after: 1500 });
await say(6, 'C\'est envoyé !', 'Le service achats reçoit votre demande, classée par fournisseur. Vous n\'avez rien d\'autre à faire.', 3500);

// 7. Suivi
await click('a[href*="r=requests"]', { nav: true, after: 800 });
await say(7, 'Suivre vos demandes', '« Suivi des demandes » indique pour chaque article : en attente, commandé, reçu… Une notification vous prévient à chaque étape.', 5000);
await moveTo(p.locator('[data-bell]'));
await say(7, 'Notifications', 'La cloche affiche vos nouvelles notifications (commande passée, livraison reçue…).', 3500);

// 8. Réception
await click('a[href*="r=receptions"]', { nav: true, after: 800 });
await say(8, 'Réceptionner une livraison', 'À l\'arrivée des colis, ouvrez la livraison attendue.', 2500);
await click('a[href*="r=reception&"], a[href*="r=reception&id"]', { nav: true, after: 800 });
await say(8, 'Scanner les colis', '« Scanner les articles livrés » : chaque code-barres lu coche une unité sur la bonne ligne.', 1200);
await click('[data-recv-scan]', { after: 3500 });
await p.locator('.scanner [data-close]').click().catch(() => {}); await wait(800);
await say(8, 'Enregistrer la réception', 'Vérifiez les quantités puis « Enregistrer la réception » : le stock du centre est mis à jour automatiquement.', 4500);

// 9. Inventaire tablette
await p.goto(APP + 'stock'); await wait(600);
await say(9, 'L\'inventaire du centre', 'La page « Inventaire » montre le stock de chaque article, son emplacement et les stocks bas.', 4000);
await click('a[href*="r=stock/quick"]', { nav: true, after: 600 });
await say(9, 'Inventaire tablette', 'Sur tablette : scannez un article, saisissez la quantité présente sur l\'étagère, validez.', 1500);
await click('[data-quick-scan]', { after: 0 });
await p.waitForSelector('[data-quick-card]:not(.hidden)', { timeout: 15000 }); await wait(800);
const qty = p.locator('[data-q-qty]'); const cur = parseInt(await qty.inputValue(), 10) || 0;
await moveTo(qty); await qty.fill(String(cur + 3)); await qty.dispatchEvent('input'); await wait(900);
await say(9, 'Entrées et sorties calculées', 'Inutile de compter ce qui est sorti : l\'écart est calculé tout seul (ici une entrée de 3).', 3500);
await click('[data-q-go]', { after: 0 });
await say(9, 'Article suivant', 'Le scanner se relance aussitôt pour l\'article suivant. Un historique s\'affiche en bas de l\'écran.', 2200);
await p.locator('.scanner [data-close]').click().catch(() => {}); await wait(400);
await moveTo(p.locator('[data-quick-log] li').first()); await wait(2200);

// 10. Aide
await p.goto(APP + 'dashboard'); await wait(500);
await say(10, 'Besoin d\'aide ?', 'Le bouton « Aide » répond tout de suite aux questions courantes.', 1200);
await click('[data-help-open]', { after: 800 });
await type('[data-help-form] input[type=text]', 'comment scanner un code-barres ?');
await p.keyboard.press('Enter'); await wait(2500);
await say(10, 'Un conseiller si besoin', 'Pas de réponse ? « Parler à un conseiller » ouvre une conversation directe avec l\'équipe NLapps.', 5000);
await say(0, '', '', 0);

// ---------------------------------------------------------------- Fin
await card('À vous de jouer !', 'Rechercher ou scanner → Ajouter → Envoyer la demande<br>Suivre · Réceptionner · Compter le stock<br><span style="font-size:17px;opacity:.75">Une question ? Bouton « Aide » en bas à droite de l\'écran</span>', 6000);
console.log('durée ~', Math.round((Date.now() - t0) / 1000), 's');
const vid = await p.video().path();
await ctx.close(); await b.close();
console.log('VIDEO', vid);
