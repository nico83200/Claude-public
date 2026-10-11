import { chromium } from 'playwright';
const F = process.argv[2], OUT = process.argv[3] || F;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const mm = (v) => Math.round(v / 25.4 * 96);
for (const [file, out, w, h] of [['affiche-fonds-perdus.html', 'affiche-equitalyon-A5-fonds-perdus.pdf', 154, 216], ['affiche-a5.html', 'affiche-equitalyon-A5.pdf', 148, 210]]) {
  const p = await b.newPage({ viewport: { width: mm(w), height: mm(h) }, deviceScaleFactor: 1 });
  await p.goto('file://' + F + '/' + file); await p.waitForTimeout(400);
  await p.pdf({ path: OUT + '/' + out, preferCSSPageSize: true, printBackground: true, pageRanges: '1' });
  await p.close();
}
// Aperçu PNG (format fini, environ 300 ppp)
const p = await b.newPage({ viewport: { width: mm(148), height: mm(210) }, deviceScaleFactor: 3.125 });
await p.goto('file://' + F + '/affiche-a5.html'); await p.waitForTimeout(400);
await p.screenshot({ path: OUT + '/affiche-equitalyon-A5-apercu.png' });
await b.close();
