import { chromium } from 'playwright';
const [F, OUT] = process.argv.slice(2);
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage();
for (const [file, out] of [['planche.html', 'cartes-equitalyon-A4-recto-verso.pdf'], ['cartes.html', 'cartes-equitalyon-imprimeur-91x61.pdf']]) {
    await p.goto('file://' + F + '/' + file); await p.waitForTimeout(500);
    await p.pdf({ path: OUT + '/' + out, preferCSSPageSize: true, printBackground: true });
}
// Aperçus PNG des deux faces de la planche
const mm = (v) => Math.round(v / 25.4 * 96);
const v = await b.newPage({ viewport: { width: mm(210), height: mm(297) }, deviceScaleFactor: 2 });
await v.goto('file://' + F + '/planche.html'); await v.waitForTimeout(500);
const pages = await v.$$('section.page');
await pages[0].screenshot({ path: OUT + '/apercu-recto.png' });
await pages[1].screenshot({ path: OUT + '/apercu-verso.png' });
await b.close();
