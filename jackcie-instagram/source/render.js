const { chromium } = require('/opt/node-tools/node_modules/playwright');
const fs = require('fs'), path = require('path');
const A = path.resolve(__dirname, 'assets');
const OUT = process.argv[2];
const nb = s => s.replace(/ ([:;?!])/g, ' $1').replace(/'/g, '’');
const slides = [
  { n: 1, file: 'jackcie-07-demi-pension', theme: 'dark', kicker: 'Demi-pension', h: 'Partagez, ', acc: 'sans tout partager.', body: "Invitez votre demi-pensionnaire : vous choisissez ce qu'il voit, ce qu'il modifie, et jusqu'à quand." },
  { n: 2, file: 'jackcie-06-budget-pension', theme: 'light', kicker: 'Budget', h: 'La pension, ', acc: 'sous contrôle.', body: "Dépenses récurrentes, rappel à chaque échéance et paiements suivis d'un simple toucher." },
  { n: 3, file: 'jackcie-05-agenda', theme: 'dark', kicker: 'Agenda', h: 'Tout le planning ', acc: 'au même endroit.', body: "Cours, séances, maréchal, vétérinaire : l'agenda de votre cheval se remplit tout seul." },
  { n: 4, file: 'jackcie-04-bilan-cours', theme: 'light', kicker: 'Après le cours', h: 'Ne perdez plus ', acc: 'le fil de vos progrès.', body: "Après chaque cours, une notification vous invite à noter exercices, ressenti et points à retravailler.", notif: true },
  { n: 5, file: 'jackcie-03-preparer-seance', theme: 'dark', kicker: 'Séances', fs: 90, h: 'Préparez votre<br>séance ', acc: 'en un glisser.', body: "Échauffement, travail, retour au calme : 125 exercices prêts à l'emploi, ou les vôtres." },
];
const S = 1.32; // phone scale vs. source mockup (410 px wide)
const PW = Math.round(410 * S);
const html = s => `<!doctype html><html><head><meta charset="utf-8"><style>
:root{--bg:#faf7f1;--title:#0d3122;--acc:#a8875e;--body:#375447;--kick:#a8875e;--brand:#0d3122;--ctabg:#0d3122;--ctafg:#fff}
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
.stage{position:relative;flex:1;margin:${s.notif ? 230 : 64}px -72px 0}
.phone{position:absolute;left:${(1080 - PW) / 2}px;top:0;width:${PW}px;filter:drop-shadow(0 30px 50px rgba(0,0,0,${s.theme === 'dark' ? .45 : .16}))}
.notif{position:absolute;left:${(1080 - PW) / 2 - Math.round(70 * S)}px;top:${-Math.round(130 * S)}px;width:${Math.round(470 * S)}px;filter:drop-shadow(0 18px 36px rgba(0,0,0,.14))}
.cta{position:absolute;left:50%;transform:translateX(-50%);bottom:72px;white-space:nowrap;background:var(--ctabg);color:var(--ctafg);
 font-weight:700;font-size:31px;letter-spacing:-.01em;padding:26px 44px;border-radius:999px;box-shadow:0 14px 40px rgba(0,0,0,.35)}
</style></head><body><div class="page ${s.theme}">
<div class="brand"><img src="file://${A}/icon-${s.theme}.png"><span>Jack&amp;Cie</span></div>
<div class="kicker">${s.kicker}</div>
<h1>${nb(s.h)}<em>${nb(s.acc)}</em></h1>
<p>${nb(s.body)}</p>
<div class="stage"><img class="phone" src="file://${A}/phone${s.n}.png">${s.notif ? `<img class="notif" src="file://${A}/notif4.png">` : ''}</div>
<div class="cta">Essai gratuit 14 jours · lien en bio</div>
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
