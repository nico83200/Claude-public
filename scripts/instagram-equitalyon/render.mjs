import { chromium } from 'playwright';
const [F, OUT] = process.argv.slice(2);
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
for (const [name, w, h] of [['jackcie-equitalyon-post', 1080, 1350], ['jackcie-equitalyon-story', 1080, 1920]]) {
  const p = await b.newPage({ viewport: { width: w, height: h } });
  await p.goto('file://' + F + '/' + name + '.html'); await p.waitForTimeout(400);
  await p.screenshot({ path: OUT + '/' + name + '.png' });
  await p.close();
}
await b.close();
