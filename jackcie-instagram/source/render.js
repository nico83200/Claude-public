// Génère les visuels Instagram Jack&Cie en 1080 × 1440 (3:4).
// Usage : node render.js <dossier de sortie>
const { chromium } = require('/opt/node-tools/node_modules/playwright');
const fs = require('fs'), path = require('path');
const A = path.resolve(__dirname, 'assets');
const OUT = process.argv[2];
const nb = s => s.replace(/ ([:;?!])/g, ' $1').replace(/'/g, '’');
// overlay : image posée par-dessus le téléphone, en px source relatifs au coin haut-gauche du téléphone
const slides = [
  { n: 10, file: 'jackcie-01-assistant', theme: 'dark', kicker: 'Assistant vocal', h: 'Dites-le.<br>', acc: "C'est noté.", body: "Une consigne à voix haute, et la séance est créée et ajoutée à l'agenda.",
    S: 1.24, gap: 64, phoneTop: 100, ctaBottom: 56, overlay: { img: 'bubble10.png', x: -50, y: 0, w: 440, s: 1.15, center: true } },
  { n: 9, file: 'jackcie-02-rappels-sante', theme: 'light', kicker: 'Santé', h: 'Le maréchal ?<br>', acc: 'On vous le rappelle.', body: 'Maréchal, vaccins, vermifuges : des rappels automatiques, au rythme de votre cheval.' },
  { n: 5, file: 'jackcie-03-preparer-seance', theme: 'dark', kicker: 'Séances', fs: 90, h: 'Préparez votre<br>séance ', acc: 'en un glisser.', body: "Échauffement, travail, retour au calme : 125 exercices prêts à l'emploi, ou les vôtres." },
  { n: 4, file: 'jackcie-04-bilan-cours', theme: 'light', kicker: 'Après le cours', h: 'Ne perdez plus ', acc: 'le fil de vos progrès.', body: 'Après chaque cours, une notification vous invite à noter exercices, ressenti et points à retravailler.',
    gap: 230, overlay: { img: 'notif4.png', x: -70, y: -130, w: 470 } },
  { n: 3, file: 'jackcie-05-agenda', theme: 'dark', kicker: 'Agenda', h: 'Tout le planning ', acc: 'au même endroit.', body: "Cours, séances, maréchal, vétérinaire : l'agenda de votre cheval se remplit tout seul." },
  { n: 2, file: 'jackcie-06-budget-pension', theme: 'light', kicker: 'Budget', h: 'La pension, ', acc: 'sous contrôle.', body: "Dépenses récurrentes, rappel à chaque échéance et paiements suivis d'un simple toucher." },
  { n: 1, file: 'jackcie-07-demi-pension', theme: 'dark', kicker: 'Demi-pension', h: 'Partagez, ', acc: 'sans tout partager.', body: "Invitez votre demi-pensionnaire : vous choisissez ce qu'il voit, ce qu'il modifie, et jusqu'à quand." },
  { n: 8, file: 'jackcie-08-alimentation', theme: 'light', kicker: 'Alimentation', h: 'La ration, ', acc: 'sous les yeux de tous.', body: 'Quantités, horaires, compléments et consignes : chacun sait exactement quoi donner.' },
  { n: 7, file: 'jackcie-09-ecurie', theme: 'dark', kicker: 'Écuries', h: "Toute l'écurie, ", acc: 'simplement.', body: 'Membres, rôles, chevaux en pension : chacun accède à ce dont il a besoin, rien de plus.' },
  { n: 6, file: 'jackcie-10-essai-gratuit', theme: 'dark', kicker: 'Jack&amp;Cie', fs: 96, h: 'Toute la vie<br>de votre cheval,<br>', acc: 'dans votre poche.', body: "Installez l'application en quelques secondes, sans passer par un store.",
    cta: false, offer: true },
];
const css = s => `
:root{--title:#0d3122;--acc:#a8875e;--body:#375447;--kick:#a8875e;--brand:#0d3122;--ctabg:#0d3122;--ctafg:#fff}
.dark{--title:#fff;--acc:#d1bb9d;--body:#d4dcd8;--kick:#d1bb9d;--brand:#fff;--ctabg:#fff;--ctafg:#0d3122}
*{margin:0;padding:0;box-sizing:border-box}
body{width:1080px;height:1440px;overflow:hidden;font-family:Inter,sans-serif;-webkit-font-smoothing:antialiased}
.page{position:relative;width:1080px;height:1440px;overflow:hidden;display:flex;flex-direction:column;padding:72px 72px 0;
 background:radial-gradient(900px 700px at 100% 0%,#f1e8d9 0%,rgba(241,232,217,0) 70%),#faf7f1}
.page.dark{background:radial-gradient(900px 800px at 100% 0%,rgba(52,96,74,.85) 0%,rgba(52,96,74,0) 70%),
 linear-gradient(165deg,#1f5e43 0%,#134030 32%,#0b2d1f 62%,#08231a 100%)}
.brand{display:flex;align-items:center;gap:16px}
.brand img{width:60px;height:60px;border-radius:14px;box-shadow:0 6px 18px rgba(0,0,0,.18)}
.brand span{font-weight:700;font-size:35px;letter-spacing:-.02em;color:var(--brand)}
.kicker{margin-top:80px;font-weight:600;font-size:25px;letter-spacing:.16em;text-transform:uppercase;color:var(--kick)}
h1{margin-top:18px;font-weight:700;font-size:${s.fs || 100}px;line-height:1.0;letter-spacing:-.04em;color:var(--title)}
h1 em{font-style:normal;color:var(--acc)}
p{margin-top:28px;font-size:33px;line-height:1.38;color:var(--body);max-width:900px;letter-spacing:-.005em}
.stage{position:relative;flex:1;margin:${s.gap ?? 64}px -72px 0}
.ov{position:absolute;filter:drop-shadow(0 18px 36px rgba(0,0,0,${s.theme === 'dark' ? .35 : .14}))}
.phone{position:absolute;filter:drop-shadow(0 30px 50px rgba(0,0,0,${s.theme === 'dark' ? .45 : .16}))}
.cta{position:absolute;left:50%;transform:translateX(-50%);bottom:${s.ctaBottom || 72}px;white-space:nowrap;background:var(--ctabg);color:var(--ctafg);
 font-weight:700;font-size:31px;letter-spacing:-.01em;padding:26px 44px;border-radius:999px;box-shadow:0 14px 40px rgba(0,0,0,.35)}
.offers{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:64px}
.card{border-radius:32px;padding:36px 34px;background:linear-gradient(160deg,rgba(255,255,255,.13),rgba(255,255,255,.05));border:1.5px solid rgba(255,255,255,.14)}
.card .k{font-weight:600;font-size:22px;letter-spacing:.14em;text-transform:uppercase;color:var(--kick)}
.card .big{margin-top:14px;font-weight:700;font-size:76px;letter-spacing:-.04em;line-height:1;color:#fff}
.card .t{margin-top:18px;font-size:27px;line-height:1.38;color:var(--body)}
.qr{display:flex;align-items:center;gap:36px;margin-top:40px}
.qr .code{background:#fff;border-radius:28px;padding:22px;box-shadow:0 14px 40px rgba(0,0,0,.3)}
.qr .code img{display:block;width:232px;height:232px}
.qr div.t{font-size:34px;line-height:1.38;color:var(--body)}
.qr b{color:#fff;font-weight:700}`;
function stage(s) {
  if (s.offer) return `<div class="offers">
 <div class="card"><div class="k">Version gratuite</div><div class="big">0&nbsp;€</div><div class="t">Pour suivre un cheval, sans limite de durée.</div></div>
 <div class="card"><div class="k">Toutes les offres</div><div class="big">14 jours</div><div class="t">${nb("d'essai gratuit : plusieurs chevaux, assistant vocal, budget, écurie.")}</div></div></div>
 <div class="qr"><div class="code"><img src="file://${A}/qr.svg"></div><div class="t">Scannez pour installer<br><b>jackcie.nlapps.fr</b></div></div>`;
  const S = s.S || 1.32, PW = Math.round(410 * S), left = (1080 - PW) / 2, top = s.phoneTop || 0;
  let ov = '';
  if (s.overlay) {
    const o = s.overlay, os = o.s || S, w = Math.round(o.w * os);
    const ol = o.center ? (1080 - w) / 2 : left + Math.round(o.x * S);
    const ot = o.center ? 0 : top + Math.round(o.y * S);
    ov = `<img class="ov" style="left:${ol}px;top:${ot}px;width:${w}px" src="file://${A}/${o.img}">`;
  }
  return `<div class="stage"><img class="phone" style="left:${left}px;top:${top}px;width:${PW}px" src="file://${A}/phone${s.n}.png">${ov}</div>`;
}
const html = s => `<!doctype html><html><head><meta charset="utf-8"><style>${css(s)}</style></head><body><div class="page ${s.theme}">
<div class="brand"><img src="file://${A}/icon-${s.theme}.png"><span>Jack&amp;Cie</span></div>
<div class="kicker">${s.kicker}</div>
<h1>${nb(s.h)}<em>${nb(s.acc)}</em></h1>
<p>${nb(s.body)}</p>
${stage(s)}
${s.cta === false ? '' : `<div class="cta">${s.cta || 'Essai gratuit 14 jours · lien en bio'}</div>`}
</div></body></html>`;
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1080, height: 1440 } });
  for (const s of slides) {
    const f = path.join(__dirname, `s${s.n}.html`); fs.writeFileSync(f, html(s));
    await p.goto('file://' + f); await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: path.join(OUT, s.file + '.png') });
  }
  await b.close();
})();
