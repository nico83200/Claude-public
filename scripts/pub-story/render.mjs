// Publicité story : node scripts/pub-story/render.mjs → jackcie-pub-story.png (1080 × 1920)
import { chromium } from 'playwright';
const D = new URL('.', import.meta.url).pathname;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + D + 'story.html'); await p.waitForTimeout(500);
await p.screenshot({ path: D + 'jackcie-pub-story.png' });
await b.close();
