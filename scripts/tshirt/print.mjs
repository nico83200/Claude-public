import { chromium } from 'playwright';
import { copyFileSync } from 'fs';
const [F, OUT] = process.argv.slice(2);
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const px = (mm) => Math.round(mm / 25.4 * 96);
for (const [name, w, h] of [['devant', 280, 210], ['dos', 300, 400]]) {
  const p = await b.newPage({ viewport: { width: px(w), height: px(h) }, deviceScaleFactor: 300 / 96 });
  await p.goto(`file://${F}/${name}.html`); await p.waitForTimeout(400);
  await p.pdf({ path: `${OUT}/tshirt-${name}.pdf`, preferCSSPageSize: true, printBackground: true });
  await p.screenshot({ path: `${OUT}/tshirt-${name}-300ppp.png`, omitBackground: true });
  copyFileSync(`${OUT}/tshirt-${name}-300ppp.png`, `${F}/${name}.png`);
  await p.close();
}
const s = await b.newPage({ viewport: { width: 1500, height: 680 }, deviceScaleFactor: 1.5 });
await s.goto(`file://${F}/simulation.html`); await s.waitForTimeout(600);
await s.screenshot({ path: `${OUT}/tshirt-simulation.png`, fullPage: true });
await b.close();
