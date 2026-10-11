// Rendu image par image de stage.html (1080×1920, 30 i/s).
// Usage : node render.mjs <dossier-de-sortie> [t1,t2,…]  (avec une liste de temps : captures d'aperçu seulement)
import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = resolve(process.argv[2] || HERE + '/out'), FPS = 30;
const only = process.argv[3] ? process.argv[3].split(',').map(Number) : null;
mkdirSync(OUT + '/frames', { recursive: true });
const horse = readFileSync(HERE + '/horse.svg', 'utf8').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + HERE + '/stage.html');
await p.evaluate((h) => { document.getElementById('horse').innerHTML = h; }, horse);
await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
const times = only ?? Array.from({ length: Math.ceil(await p.evaluate(() => window.DURATION) * FPS) }, (_, i) => i / FPS);
for (const [i, t] of times.entries()) {
  await p.evaluate((t) => render(t), t);
  await p.screenshot({ path: only ? `${OUT}/apercu-${t}.png` : `${OUT}/frames/${String(i).padStart(4, '0')}.png` });
}
await b.close();
console.log(times.length + ' images');
