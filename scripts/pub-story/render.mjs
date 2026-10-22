// Publicité story : node scripts/pub-story/render.mjs → jackcie-pub-story.png et jackcie-pub-story-photo.png (1080 × 1920)
import { chromium } from 'playwright';
const D = new URL('.', import.meta.url).pathname;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
await p.goto('file://' + D + 'story.html'); await p.waitForTimeout(500);
await p.screenshot({ path: D + 'jackcie-pub-story.png' });
// Variante avec la photo de la cavalière à la place du téléphone.
await p.goto('file://' + D + 'story.html#photo'); await p.reload(); await p.waitForTimeout(500);
await p.screenshot({ path: D + 'jackcie-pub-story-photo.png' });
// Deuxième photo (cavalière en extérieur).
await p.goto('file://' + D + 'story.html#photo2'); await p.reload(); await p.waitForTimeout(500);
await p.screenshot({ path: D + 'jackcie-pub-story-photo-2.png' });
await b.close();
// Nouvelle version (story-2.html) : fond vert, les deux photos en tirages, notifications de l'appli.
{ const b2 = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p2 = await b2.newPage({ viewport: { width: 1080, height: 1920 } });
  await p2.goto('file://' + D + 'story-2.html'); await p2.waitForTimeout(800);
  await p2.screenshot({ path: D + 'jackcie-pub-story-v2.png' }); await b2.close(); }
// Variantes avec les utilisatrices de l'appli : story-3 (fenêtre, fond vert), story-4 (fond beige) ; versions plus masculines : story-5 (manège), story-6 (cavalier barbu).
{ const b3 = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p3 = await b3.newPage({ viewport: { width: 1080, height: 1920 } });
  for (const n of [3, 4, 5, 6]) { await p3.goto('file://' + D + 'story-' + n + '.html'); await p3.waitForTimeout(800); await p3.screenshot({ path: D + 'jackcie-pub-story-v' + n + '.png' }); }
  await b3.close(); }
// Format fil Instagram 4:5 (1080 × 1350) des versions 2 à 6 : feed-N.html → jackcie-pub-feed-vN.png.
{ const b4 = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p4 = await b4.newPage({ viewport: { width: 1080, height: 1350 } });
  for (const n of [2, 3, 4, 5, 6]) { await p4.goto('file://' + D + 'feed-' + n + '.html'); await p4.waitForTimeout(800); await p4.screenshot({ path: D + 'jackcie-pub-feed-v' + n + '.png' }); }
  await b4.close(); }
