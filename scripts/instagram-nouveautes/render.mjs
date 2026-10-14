// Rendu des publications 3:4 : node scripts/instagram-nouveautes/render.mjs → publications/*.png (1080 × 1440)
import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'node:fs';
const D = new URL('.', import.meta.url).pathname;
mkdirSync(D + 'publications', { recursive: true });
const posts = JSON.parse(readFileSync(D + 'posts.json'));
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1440 } });
for (const post of posts) {
    await p.goto('file://' + D + 'post.html#' + encodeURIComponent(JSON.stringify(post)));
    await p.reload(); await p.waitForTimeout(400);
    await p.screenshot({ path: `${D}publications/jackcie-${post.id}.png` });
    console.log('✓', post.id);
}
await b.close();
