import { chromium } from 'playwright';
const F = process.argv[2];
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
// PDF d'impression : page à la taille A3 (densité 1) pour une mise en page à l'échelle exacte
const pp = await b.newPage({ viewport: { width: 1123, height: 1587 }, deviceScaleFactor: 1 });
await pp.goto('file://' + F + '/affiche.html'); await pp.waitForTimeout(400);
await pp.pdf({ path: F + '/affiche-jackcie-A3.pdf', preferCSSPageSize: true, printBackground: true, pageRanges: '1' });
// Aperçu PNG en haute définition
const p = await b.newPage({ viewport: { width: 1123, height: 1587 }, deviceScaleFactor: 2 });
await p.goto('file://' + F + '/affiche.html'); await p.waitForTimeout(400);
await p.screenshot({ path: F + '/affiche-jackcie-apercu.png' });
await b.close();
