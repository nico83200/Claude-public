// Images de partage (Open Graph, 1200×630) : node scripts/site/og.mjs (aperçu local du site sur :8090).
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
const OUT = new URL('../../site/img/', import.meta.url).pathname;
const cards = {
    accueil: ['L\'application des propriétaires', 'Le carnet de bord de votre cheval, dans votre poche.', 'mobile-accueil'],
    ecuries: ['Jack&amp;Cie pour les écuries', 'Pension, soins, équipe et propriétaires réunis.', 'mobile-pension'],
    tarifs: ['Gratuit pour un cheval', 'Dès 6,90 € par mois, sans engagement.', 'mobile-sante'],
};
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1200, height: 630 } });
for (const [name, [eyebrow, title, shot]] of Object.entries(cards)) {
    await p.goto('http://127.0.0.1:8090/404');
    await p.setContent(`<!doctype html><html><head><link rel="stylesheet" href="http://127.0.0.1:8090/css/site.css"></head>
    <body style="margin:0;width:1200px;height:630px;overflow:hidden;background:#0d3122;position:relative">
      <div style="position:absolute;width:700px;height:700px;right:-120px;top:-260px;border-radius:50%;background:radial-gradient(closest-side,rgba(168,135,94,.4),transparent)"></div>
      <div style="position:absolute;left:72px;top:70px;width:640px">
        <img src="http://127.0.0.1:8090/img/brand/jackcie-wordmark.webp" style="height:58px;filter:brightness(0) invert(1)">
        <p style="margin:64px 0 14px;color:#e7dac3;font:700 22px Inter;letter-spacing:.08em;text-transform:uppercase">${eyebrow}</p>
        <h1 style="color:#fff;font-size:64px;line-height:1.08;margin:0">${title}</h1>
        <p style="color:rgba(255,255,255,.72);font:500 26px Inter;margin-top:34px">jackcie.fr</p>
      </div>
      <div class="phone" style="position:absolute;right:90px;top:70px;width:300px;transform:rotate(4deg)"><img src="http://127.0.0.1:8090/img/app/${shot}.webp"></div>
    </body></html>`, { waitUntil: 'networkidle' });
    await p.screenshot({ path: OUT + `og-${name}.png` });
    execFileSync('convert', [OUT + `og-${name}.png`, '-quality', '86', OUT + `og-${name}.jpg`]);
    execFileSync('rm', [OUT + `og-${name}.png`]);
    console.log('✓ og-' + name);
}
await b.close();
