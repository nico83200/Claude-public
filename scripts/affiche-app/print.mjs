import { chromium } from 'playwright';
const F = process.argv[2], OUT = process.argv[3] || F;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const mm = (v) => Math.round(v / 25.4 * 96);
for (const [file, out, w, h] of [['affiche-fonds-perdus.html', 'affiche-jackcie-A4-fonds-perdus.pdf', 216, 303], ['affiche-a4.html', 'affiche-jackcie-A4.pdf', 210, 297]]) {
  const p = await b.newPage({ viewport: { width: mm(w), height: mm(h) }, deviceScaleFactor: 1 });
  await p.goto('file://' + F + '/' + file); await p.waitForTimeout(400);
  await p.pdf({ path: OUT + '/' + out, preferCSSPageSize: true, printBackground: true, pageRanges: '1' });
  await p.close();
}
// Aperçu PNG (format fini, environ 300 ppp)
const p = await b.newPage({ viewport: { width: mm(210), height: mm(297) }, deviceScaleFactor: 2.5 });
await p.goto('file://' + F + '/affiche-a4.html'); await p.waitForTimeout(400);
await p.screenshot({ path: OUT + '/affiche-jackcie-A4-apercu.png' });
await b.close();
