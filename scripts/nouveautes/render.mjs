// Rendu image par image de stage.html (1080×1920, 30 i/s) à partir des captures de capture.mjs.
// Usage : node scripts/nouveautes/render.mjs <dossier-captures> <dossier-sortie> [t1,t2,…]
import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const HERE = dirname(fileURLToPath(import.meta.url)), FPS = 30;
const SHOTS = resolve(process.argv[2]), OUT = resolve(process.argv[3]);
const only = process.argv[4] ? process.argv[4].split(',').map(Number) : null;
mkdirSync(OUT + '/frames', { recursive: true });
const scenes = JSON.parse(readFileSync(HERE + '/scenes.json', 'utf8'));
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--allow-file-access-from-files'] });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + HERE + '/stage.html');
await p.evaluate(([s, d]) => window.setup(s, d), [scenes, SHOTS]);
await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
const times = only ?? Array.from({ length: Math.ceil(await p.evaluate(() => window.DURATION) * FPS) }, (_, i) => i / FPS);
for (const [i, t] of times.entries()) {
  await p.evaluate((t) => render(t), t);
  await p.screenshot({ path: only ? `${OUT}/apercu-${t}.png` : `${OUT}/frames/${String(i).padStart(4, '0')}.png` });
}
await b.close();
console.log(times.length + ' images');
