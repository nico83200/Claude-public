// Génère les visuels PNG de la marque à partir du site en marche (mêmes SVG, mêmes polices) :
//   public/email/*.png (emails), app/apple-icon.png, app/opengraph-image.png
//
// Prérequis : le site lancé (npm run build && APP_URL=https://votre-domaine npm start),
// puis : npm i --no-save playwright-core && npx playwright-core install chromium
// Usage  : node --experimental-strip-types scripts/generer-visuels.mjs [http://localhost:3000] [chemin/vers/chrome]
import fs from "node:fs";
import path from "node:path";
import { chromium } from "playwright-core";

const SITE = process.argv[2] ?? "http://localhost:3000";
const CHROME = process.argv[3];
const RACINE = path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const sortie = (f) => path.join(RACINE, f);

const navigateur = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
const page = await (await navigateur.newContext({ viewport: { width: 1400, height: 1000 }, deviceScaleFactor: 2 })).newPage();
await page.goto(SITE + "/");
const svg = await page.evaluate(() => ({
  basicNoir: document.querySelector(".hero-cards .front svg").outerHTML,
  basicBlanc: document.querySelector(".hero-cards .back svg").outerHTML,
  versoNoir: document.querySelectorAll(".spotlight-visual svg")[1].outerHTML,
  perso: [...document.querySelectorAll(".showcase svg")].map((s) => s.outerHTML),
  symbole: document.querySelector(".site-header .logo-mark").outerHTML.replace(/width="\d+" height="\d+"/, 'width="100%" height="100%"'),
  css: [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.href),
}));

const carte = (s, style) => `<div style="position:absolute;border-radius:4%/6.3%;overflow:hidden;${style}">${s.replace(/<svg /, '<svg style="display:block;width:100%;height:100%" ')}</div>`;
const lockup = (couleur, h) => `<div style="display:flex;align-items:center;gap:${h * 0.28}px;height:${h}px"><div style="width:${h}px;height:${h}px">${svg.symbole}</div><span style="font-family:var(--font);font-weight:800;letter-spacing:-.017em;font-size:${h * 0.62}px;color:${couleur};line-height:1">Tapikard</span></div>`;

// Compositions : [fichier, largeur, hauteur, HTML, fond transparent ?]
const visuels = [
  ["public/email/logo.png", 148, 36, lockup("#121117", 36), true],
  ["public/email/logo-blanc.png", 148, 36, lockup("#ffffff", 36), true],
  ["public/email/symbole.png", 28, 28, `<div style="width:28px;height:28px">${svg.symbole}</div>`, true],
  ["public/email/carte-basic.png", 220, 160, carte(svg.basicNoir, "left:10px;top:12px;width:200px;aspect-ratio:856/540;transform:rotate(-6deg);box-shadow:0 14px 26px -12px rgba(0,0,0,.55)"), true],
  ["public/email/cartes-hero.png", 504, 300,
    carte(svg.basicBlanc, "right:26px;top:88px;width:280px;aspect-ratio:856/540;transform:rotate(7deg);box-shadow:0 30px 50px -24px rgba(0,0,0,.8)") +
    carte(svg.basicNoir, "left:28px;top:28px;width:320px;aspect-ratio:856/540;transform:rotate(-5deg);box-shadow:0 0 0 1px rgba(255,255,255,.12),0 34px 60px -24px rgba(0,0,0,.9)"), true],
  ["public/email/carte-basic-duo.png", 504, 424,
    carte(svg.basicNoir, "left:10px;top:8px;width:300px;aspect-ratio:856/540;box-shadow:0 18px 34px -16px rgba(18,17,23,.5)") +
    carte(svg.versoNoir, "right:10px;top:214px;width:300px;aspect-ratio:856/540;box-shadow:0 18px 34px -16px rgba(18,17,23,.5)"), true],
  ["public/email/cartes-perso.png", 230, 170,
    carte(svg.perso[2], "left:70px;top:30px;width:150px;aspect-ratio:856/540;transform:rotate(8deg);box-shadow:0 12px 22px -10px rgba(0,0,0,.5)") +
    carte(svg.perso[1], "left:40px;top:52px;width:150px;aspect-ratio:856/540;transform:rotate(1deg);box-shadow:0 12px 22px -10px rgba(0,0,0,.5)") +
    carte(svg.perso[0], "left:8px;top:74px;width:150px;aspect-ratio:856/540;transform:rotate(-6deg);box-shadow:0 12px 22px -10px rgba(0,0,0,.5)"), true],
  ["app/opengraph-image.png", 1200, 630, `<div style="position:absolute;inset:0;background:#0e0d13;overflow:hidden;color:#fff;font-family:var(--font)">
    <div style="position:absolute;right:-120px;top:-160px;width:820px;height:900px;background:radial-gradient(closest-side,rgba(91,61,245,.55),transparent 70%)"></div>
    <div style="position:absolute;left:72px;top:64px">${lockup("#ffffff", 48)}</div>
    <div style="position:absolute;left:72px;top:190px;width:560px"><div style="font-family:var(--font-display);font-size:76px;line-height:.98;letter-spacing:-.015em">Votre carte de visite, <em style="color:#a99bff">d’un simple geste.</em></div>
    <div style="margin-top:30px;font-size:24px;color:#a9a5b4">Carte NFC · noir mat ou blanc · dès 19 €</div></div>
    ${carte(svg.basicBlanc, "right:40px;top:270px;width:380px;aspect-ratio:856/540;transform:rotate(7deg);box-shadow:0 40px 80px -30px rgba(0,0,0,.8)")}
    ${carte(svg.basicNoir, "right:300px;top:180px;width:410px;aspect-ratio:856/540;transform:rotate(-5deg);box-shadow:0 0 0 1px rgba(255,255,255,.1),0 50px 90px -30px rgba(0,0,0,.9)")}</div>`, false],
];

for (const [fichier, l, h, html, transparent] of visuels) {
  const p = await (await navigateur.newContext({ viewport: { width: l, height: h }, deviceScaleFactor: fichier.startsWith("app/") ? 1 : 2 })).newPage();
  await p.setContent(`<html><head>${svg.css.map((c) => `<link rel="stylesheet" href="${c}">`).join("")}</head>
    <body style="margin:0;background:transparent"><div id="v" style="position:relative;width:${l}px;height:${h}px">${html}</div></body></html>`, { waitUntil: "load" });
  await p.evaluate(() => document.fonts.ready);
  await p.locator("#v").screenshot({ path: sortie(fichier), omitBackground: transparent });
  console.log("✓", fichier);
}

// Icône iPhone : symbole plein cadre (iOS arrondit les coins)
const brand = await import(path.join(RACINE, "lib/brand.ts")).catch(() => null);
if (brand) {
  const p = await (await navigateur.newContext({ viewport: { width: 180, height: 180 } })).newPage();
  await p.setContent(`<body style="margin:0">${brand.brandMarkSvg({ square: true }).replace("<svg ", '<svg width="180" height="180" ')}</body>`);
  await p.screenshot({ path: sortie("app/apple-icon.png") });
  console.log("✓ app/apple-icon.png");
}
await navigateur.close();
