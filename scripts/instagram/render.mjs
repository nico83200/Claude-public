import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'node:fs';
const D = process.argv[2]; mkdirSync(D + '/out', { recursive: true });
const posts = JSON.parse(readFileSync(D + '/posts.json'));
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1080 } });
for (const post of posts) {
  await p.goto('file://' + D + '/post.html#' + encodeURIComponent(JSON.stringify(post)));
  await p.reload(); await p.waitForTimeout(350);
  await p.screenshot({ path: `${D}/out/jackcie-${post.id}.png` });
}
await b.close(); console.log('ok');
