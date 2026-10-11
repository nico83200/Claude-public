import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const P = process.argv[2], FPS = 30, DUR = 24;
mkdirSync(P + '/frames', { recursive: true });
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + P + '/stage.html'); await p.waitForTimeout(500);
// contour du téléphone (transparent)
await p.evaluate(() => { document.body.classList.add('bezel'); render(10); });
await p.screenshot({ path: P + '/bezel.png', omitBackground: true });
await p.evaluate(() => { document.body.classList.remove('bezel'); });
for (let i = 0; i < FPS * DUR; i++) {
  await p.evaluate((t) => render(t), i / FPS);
  await p.screenshot({ path: `${P}/frames/${String(i).padStart(4, '0')}.png` });
}
await b.close();
console.log('ok');
