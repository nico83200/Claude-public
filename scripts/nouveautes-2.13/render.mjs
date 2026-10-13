// Rendu image par image de stage.html (1080×1920, 30 i/s) à partir des captures de capture.mjs.
// Usage : node scripts/nouveautes-2.13/render.mjs <dossier-captures> <dossier-sortie> [t1,t2,…]
//         ou … --part=k/n pour ne rendre qu'une tranche d'images (rendu en parallèle)
import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const HERE = dirname(fileURLToPath(import.meta.url)), FPS = 30;
const SHOTS = resolve(process.argv[2]), OUT = resolve(process.argv[3]);
const partArg = process.argv.find((a) => a.startsWith('--part='));
const only = process.argv[4] && !process.argv[4].startsWith('--') ? process.argv[4].split(',').map(Number) : null;
mkdirSync(OUT + '/frames', { recursive: true });
const scenes = JSON.parse(readFileSync(HERE + '/scenes.json', 'utf8'));
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--allow-file-access-from-files'] });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + HERE + '/stage.html');
await p.evaluate(([s, d]) => window.setup(s, d), [scenes, SHOTS]);
await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
const count = Math.ceil(await p.evaluate(() => window.DURATION) * FPS);
let frames = Array.from({ length: count }, (_, i) => i);
if (partArg) { const [k, n] = partArg.slice(7).split('/').map(Number); frames = frames.filter((i) => i % n === k); }
const times = only ?? frames.map((i) => i / FPS);
for (const [j, t] of times.entries()) {
  await p.evaluate((t) => render(t), t);
  await p.screenshot({ path: only ? `${OUT}/apercu-${t}.png` : `${OUT}/frames/${String(frames[j]).padStart(4, '0')}.png` });
}
await b.close();
console.log(times.length + ' images');
