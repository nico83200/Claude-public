import { chromium } from 'playwright';
const F = process.argv[2];
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1123, height: 1587 }, deviceScaleFactor: 2 });
await p.goto('file://' + F + '/affiche.html'); await p.waitForTimeout(400);
await p.pdf({ path: F + '/affiche-jackcie-A3.pdf', width: '297mm', height: '420mm', printBackground: true, pageRanges: '1' });
await p.screenshot({ path: F + '/affiche-jackcie-apercu.png' });
await b.close();
