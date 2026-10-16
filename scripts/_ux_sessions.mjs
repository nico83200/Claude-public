import { chromium } from 'playwright';
import fs from 'fs';
const B='http://127.0.0.1:8013', D='/tmp/claude-0/-home-user-Claude-public/b11dbf7a-2a75-575f-a224-4c6377325b29/scratchpad/ux/sessions/';
const STATE=D+'state.json';
const step = process.argv[2] || 'a';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const desktop = process.argv[3]==='desk';
const ctxOpts = desktop ? { viewport:{width:1280,height:860} } : { viewport:{width:390,height:844}, isMobile:true, hasTouch:true, deviceScaleFactor:2 };
if (fs.existsSync(STATE)) ctxOpts.storageState = STATE;
const ctx = await browser.newContext({ ...ctxOpts, locale:'fr-FR' });
const page = await ctx.newPage();
page.on('pageerror', e=>console.log('PAGEERR', e.message));
page.on('console', m=>{ if(m.type()==='error') console.log('CONSOLE', m.text()); });
async function login(){
  await page.goto(B+'/seances');
  if (page.url().includes('/login')) {
    await page.fill('form[action$="/login"] [name=email]','cavaliere@demo.jackcie.test');
    await page.fill('form[action$="/login"] [name=password]','Demo2026!demo');
    await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button.btn-primary')]);
    await ctx.storageState({ path: STATE });
    console.log('logged in ->', page.url());
  }
}
const shot = async (n, full=true) => { await page.waitForTimeout(400); await page.screenshot({ path: D+n+'.png', fullPage: full }); console.log('shot', n, await page.evaluate(()=>document.documentElement.scrollHeight)); };
await login();
const mod = await import('./_ux_sessions_steps.mjs');
await mod[step]({ page, ctx, B, shot, D });
await ctx.storageState({ path: STATE });
await browser.close();
