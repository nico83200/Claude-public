/*
 * Vidéo tutoriel « service achats » de Centriva, enregistrée depuis l'application réelle (données de démonstration).
 * Même présentation que le tutoriel salarié (tools/tutoriel-salarie.mjs) : sous-titres, curseur visible, écrans de partie,
 * repère de synchronisation en bas à gauche pour caler la voix off avec tools/tutoriel-voix.py.
 *
 *   [VOIX=durees.json] PWPATH=/chemin/vers/playwright node tools/tutoriel-achats.mjs <dossier-sortie> [url] [email] [mot-de-passe]
 *   → la vidéo .webm, Centriva-tutoriel-achats.srt et Centriva-tutoriel-achats-voix-off.txt
 *
 * Le compte utilisé doit être administrateur (sans double authentification), avec des demandes en attente chez MédiDistrib
 * pour deux centres (dont une urgente, une ligne « Bandelettes urinaires » à refuser) et un bon reçu avec un écart de facture (BC-2026-0019).
 */
import { createRequire } from 'module'; const require = createRequire(import.meta.url); const { chromium } = require(process.env.PWPATH || 'playwright');
import fs from 'fs';
import path from 'path';
import { writeCaptions } from './tutoriel-sous-titres.mjs';
const OUT = process.argv[2];
// Durées des phrases de la voix off (secondes), si elle est déjà enregistrée : le rythme de la vidéo suit alors la voix
const VOIX = process.env.VOIX ? JSON.parse(fs.readFileSync(process.env.VOIX, 'utf8')) : null;
const APP = (process.argv[3] || 'http://127.0.0.1:8096/') + 'index.php?r=';
const EMAIL = process.argv[4] || 'achats@groupe-sante.fr', PASS = process.argv[5] || 'demo1234';
const LOGO = fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../assets/brand/centriva-logo-blanc.svg'), 'utf8');
const W = 1280, H = 720;
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: W, height: H }, recordVideo: { dir: OUT, size: { width: W, height: H } } });
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
const t0 = Date.now();
const med = () => p.locator('.supplier-block', { hasText: 'MédiDistrib' }).first();
const login = async () => {
  await p.goto(APP + 'login');
  await p.fill('input[name=email]', EMAIL); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
};
const scrollTo = (loc) => L(loc).evaluate((el) => el.scrollIntoView({ behavior: 'smooth', block: 'start' })).then(() => wait(700));

// ================================================================ Introduction (accueil de la voix off sur le repère 51)
await card('Tutoriel service achats', 'Traiter les demandes · Passer les commandes · Contrôler les factures · Piloter les achats<br><span style="font-size:17px;opacity:.75">Centriva — édité par NLapps</span>', VOIX ? Math.max(4200, VOIX.intro * 1000 + 1600) : 6000, 51);

// ================================================================ Partie 1 : pilotage
await part(1, 'Le pilotage des achats', 'Tableau de bord · Budgets · Dates limites');
await login();
await say(1, 'Le menu « Service achats »', 'Connecté avec un compte du service achats, le menu affiche la rubrique « Service achats », en plus des pages de votre centre.', { hl: '.sidebar .nav' });
for (const r of ['admin/requests', 'admin/orders']) { await moveTo(L(`.sidebar a[href$="r=${r.replace('/', '%2F')}"], .sidebar a[href$="r=${r}"]`)); await wait(300); }
await at(0.8); await click('.sidebar a[href$="r=admin"]', { nav: true, after: 200 });
await say(2, 'Le tableau de bord', 'La page « Pilotage » est votre point de départ : lignes de demande à traiter, bons à commander, livraisons en cours et dépenses du mois.', { hl: '.grid-4' });
await say(3, 'Demandes par fournisseur', 'Les demandes en attente sont regroupées par fournisseur. La jauge indique si le minimum de commande du fournisseur est atteint.', { hl: '.card:has-text("Demandes en attente par fournisseur")' });
await say(4, 'Budgets et dates limites', 'À droite, le budget de chaque centre, vos principaux fournisseurs et les prochaines dates limites de commande.', { hl: '.card:has(h2:has-text("Par centre"))' });
await at(0.6); await moveTo(L('.card:has(h2:has-text("Prochaines dates limites"))'));
await say(5, 'Ce qui demande votre attention', 'Plus bas, les hausses de prix récentes et les factures à rapprocher : tout ce qui demande votre attention est réuni ici.', { hl: '.card:has-text("Hausses de prix")' });

// ================================================================ Partie 2 : traiter les demandes
await part(2, 'Traiter les demandes', 'Minimum fournisseur · Stock des centres · Refus · Commande groupée');
await p.goto(APP + 'admin'); await wait(300);
await say(6, 'Traiter les demandes', 'Cliquez sur « Traiter les demandes » : les demandes de tous les centres sont classées par fournisseur, puis par centre.');
await at(0.35); await click('a:has-text("Traiter les demandes")', { nav: true, after: 300 });
await scrollTo(med());
const g1 = () => med().locator('form[data-po-group]').first();
await say(7, 'Minimum et franco', 'Pour chaque centre, le montant sélectionné est comparé au minimum de commande et au franco de port du fournisseur.', { hl: g1().locator('.group-head') });
await say(8, 'Le détail des demandes', 'Chaque ligne indique l\'article, le demandeur et son commentaire, la quantité et le prix. Les demandes urgentes sont signalées en rouge.', { hl: g1().locator('tbody tr').first() });
await at(0.75); await moveTo(g1().locator('.badge-red, .badge:has-text("Urgent")').first());
await say(9, 'Le stock du centre', 'La colonne « Stock centre » montre ce qu\'il reste dans le centre demandeur : sous le seuil, en rupture, ou suffisant pour couvrir la demande.', { hl: g1().locator('tbody tr', { hasText: 'Antiseptique' }) });
for (const t of ['Gants d\'examen', 'Compresses', 'Antiseptique']) { await moveTo(g1().locator('tbody tr', { hasText: t }).first().locator('td').nth(3)); await wait(900); }
await say(10, 'Un meilleur prix ailleurs', 'Quand le même article est moins cher chez un autre fournisseur, l\'économie possible est affichée sous la ligne.', { hl: g1().locator('tbody tr', { hasText: 'Moins cher' }).first() });
const g2 = () => med().locator('form[data-po-group]').nth(1);
await say(11, 'Un transfert entre centres', 'Si un autre centre a l\'article en stock, Centriva propose de le transférer plutôt que de l\'acheter.', { hl: g2().locator('tbody tr', { hasText: 'Seringues' }) });
await moveTo(g2().locator('.transfer-btn').first());
const refRow = () => g2().locator('tbody tr', { hasText: 'Bandelettes urinaires' });
await say(12, 'Refuser une ligne', 'Une ligne ne doit pas être commandée ? La croix la refuse, avec un motif que le demandeur verra dans son suivi.', { hl: refRow() });
await at(0.6); await click(refRow().locator('td').last().locator('button'), { nav: true, after: 300 });
await scrollTo(med());
await say(13, 'Garder une ligne pour plus tard', 'Pour une ligne à garder pour plus tard, décochez-la simplement : elle restera dans les demandes à traiter.');
await moveTo(g1().locator('tbody tr input[type=checkbox]').first()); await wait(700);
await say(14, 'La commande groupée', 'Plusieurs centres commandent chez le même fournisseur ? « Commande groupée » réunit leurs demandes en une seule commande, avec un bon par centre.', { hl: med().locator('button:has-text("Commande groupée")') });
await at(0.85); await click(med().locator('button:has-text("Commande groupée")'), { nav: true, after: 300 });
const groupUrl = p.url();

// ================================================================ Partie 3 : passer la commande
await part(3, 'Passer la commande', 'Bons par centre · Commande en ligne ou par e-mail · Statut « Commandé »');
await p.goto(groupUrl); await wait(300);
await say(15, 'La commande est créée', 'La commande groupée est créée : un bon par centre livré, et un seul passage de commande chez le fournisseur.', { hl: '.card:has(table)' });
await say(16, 'Seuils sur le total', 'Le minimum et le franco sont calculés sur le total groupé : vous atteignez plus vite les seuils du fournisseur.', { hl: '.card:has(table) .card-body' });
await say(17, 'Un bon par centre', 'Chaque bon reste modifiable tant que la commande n\'est pas passée : quantités, prix, frais de port et note pour le fournisseur.');
await click(p.locator('a[href*="admin/order&id"], a[href*="admin%2Forder&id"]').first(), { nav: true, after: 300 });
await moveTo(L('.qty-input, input[name^="qty"]').first()); await wait(800); await moveTo(L('textarea').first());
await hold(); await p.goto(groupUrl); await wait(300);
await say(18, 'Commander en ligne', 'Ce fournisseur prend ses commandes en ligne : ouvrez son site d\'ici, votre numéro client et les références à copier sont prêts.', { hl: '.card:has-text("Commande en ligne")' });
await say(19, 'Ou par e-mail', 'Pour un fournisseur qui travaille par e-mail, Centriva envoie le bon en PDF, directement depuis cette page.');
await click(L('summary:has-text("envoyer le PDF par e-mail"), :text("Autre possibilité")').first(), { after: 300 });
const done = 'form[action*="order-group"]';
await say(20, 'Marquer « Commandé »', 'Une fois la commande passée, notez la référence du fournisseur et la date de livraison prévue, puis marquez les bons « Commandé ».', { hl: done });
await type(`${done} input[name=supplier_reference]`, 'WEB-58213');
await L(`${done} input[name=expected_date]`).fill(new Date(Date.now() + 3 * 86400e3).toISOString().slice(0, 10)); await wait(300);
await at(0.88); await click(`${done} button[type=submit]`, { nav: true, after: 300 });
await say(21, 'Les centres sont prévenus', 'Les demandeurs sont prévenus automatiquement, et chaque centre pourra réceptionner ses colis dès leur arrivée.', { hl: '.card:has(table)' });
await hold(); await p.goto(APP + 'admin/orders'); await wait(300);
await say(22, 'Le suivi des bons', 'La page « Bons de commande » suit tous les bons, de « À commander » jusqu\'à la réception, avec l\'avancement des livraisons.', { hl: '.tabs' });
await at(0.5); await moveTo(L('table tbody tr').first());

// ================================================================ Partie 4 : factures
await part(4, 'Contrôler les factures', 'Saisie · Rapprochement · Écarts');
await p.goto(APP + 'admin/order&id=19'); await wait(300);
const inv = '.card:has(h2:has-text("Facture")), .card:has(h3:has-text("Facture"))';
await say(23, 'Saisir la facture', 'Quand la facture arrive, ouvrez le bon reçu : saisissez le numéro, la date et le montant hors taxes, puis joignez le PDF.', { hl: inv });
for (const n of ['invoice_number', 'invoice_date', 'invoice_amount', 'invoice_file']) { await moveTo(L(`input[name=${n}]`)); await wait(700); }
await say(24, 'Le rapprochement', 'Centriva compare la facture à ce qui a été réellement reçu. Un écart est signalé en rouge : vérifiez les quantités et les prix avant de payer.', { hl: `${inv} .flash-error` });
await say(25, 'Lecture par l\'IA', 'Avec l\'option assistant IA, Centriva peut aussi lire la facture et remplir ces champs pour vous.', { hl: inv });
await hold(); await p.goto(APP + 'admin/invoices'); await wait(300);
await say(26, 'Toutes les factures', 'La page « Factures » compare chaque facture aux marchandises reçues : à saisir, conformes ou en écart. Les exports comptables sont juste en dessous dans le menu.', { hl: 'table' });
await at(0.75); await moveTo(L('.sidebar a[href*="exports"]'));

// ================================================================ Partie 5 : stocks, tarifs, direction
await part(5, 'Stocks, tarifs et direction', 'Stocks des centres · Import des tarifs · Tableau de bord direction');
await p.goto(APP + 'admin/stocks'); await wait(300);
await say(27, 'Stocks des centres', '« Stocks des centres » donne la vue d\'ensemble : articles suivis, stocks bas et valeur du stock de chaque centre.', { hl: '.grid-3, .grid:has(.card:has-text("Ouvrir l\'inventaire"))' });
await say(28, 'Réapprovisionnement automatique', 'Avec le réapprovisionnement automatique, une demande est créée dès qu\'un article passe sous son seuil, et arrive dans vos demandes à traiter.', { hl: '.card:has-text("Réapprovisionnement automatique")' });
await hold(); await p.goto(APP + 'admin/products/import'); await wait(300);
await say(29, 'Mettre à jour les tarifs', 'Pour mettre à jour un catalogue, importez le fichier Excel du fournisseur : les hausses de prix sont signalées avant l\'import.', { hl: 'form[enctype]' });
await hold(); await p.goto(APP + 'admin/direction'); await wait(300);
await say(30, 'Le tableau de bord direction', 'Le tableau de bord « Direction » présente les dépenses, les économies obtenues grâce aux tarifs négociés et la valeur des stocks.', { hl: '.grid-4' });
await say(31, 'Le détail des dépenses', 'Les dépenses sont détaillées par centre, par catégorie, par fournisseur et par article, avec l\'évolution sur douze mois.');
await p.mouse.wheel(0, 650); await wait(900); await moveTo(L('.card:has-text("Par catégorie")')); await at(0.6); await p.mouse.wheel(0, 500);
await say(32, 'Le rapport mensuel', 'Chaque début de mois, un rapport PDF est créé automatiquement et peut être envoyé par e-mail à la direction.', { hl: '.card:has-text("Rapports mensuels")' });
await hold();
if (curHl) await hl(curHl, false);
await p.evaluate(() => window.__tuto && window.__tuto('', '', '', 0)); cue('end');

// ================================================================ Fin
await card('À vous de jouer !', 'Pilotage → Traiter les demandes → Commande groupée → « Commandé »<br>Réception par les centres → Contrôle des factures → Tableau de bord direction<br><span style="font-size:17px;opacity:.75">Une question ? Bouton « Aide » en bas à droite de l\'écran</span>', 5000);
cue('end');
fs.writeFileSync(path.join(OUT, 'sous-titres.json'), JSON.stringify(cues, null, 1));
writeCaptions(cues, OUT, 'Centriva-tutoriel-achats');
console.log('étapes', step, '· durée ~', Math.round((Date.now() - t0) / 1000), 's');
const vid = await p.video().path();
await ctx.close(); await b.close();
console.log('VIDEO', vid);
