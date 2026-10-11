// Images d'aperçu de la vidéo : node scripts/nouveautes/apercu.mjs <dossier-captures> <dossier-sortie>
import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const HERE = dirname(fileURLToPath(import.meta.url)), SHOTS = resolve(process.argv[2]), OUT = resolve(process.argv[3]);
const tmp = HERE + '/.apercu.tmp.html';
writeFileSync(tmp, readFileSync(HERE + '/apercu.html', 'utf8').replaceAll('SHOTS/', 'file://' + SHOTS + '/'));
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--allow-file-access-from-files'] });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + tmp); await p.waitForLoadState('networkidle');
await p.screenshot({ path: OUT + '/jackcie-nouveautes-apercu.png' });
await p.setViewportSize({ width: 1920, height: 1080 });
await p.evaluate(() => { document.body.classList.add('wide'); tall.style.display = 'none'; wide.style.display = 'block'; });
await p.screenshot({ path: OUT + '/jackcie-nouveautes-apercu-16-9.png' });
await b.close();
(await import('node:fs')).unlinkSync(tmp);
