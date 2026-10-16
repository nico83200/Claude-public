import { chromium } from 'playwright';
const OUT='/tmp/claude-0/-home-user-Claude-public/b11dbf7a-2a75-575f-a224-4c6377325b29/scratchpad/ux/health/';
const B='http://127.0.0.1:8013';
const mode=process.argv[2]||'explore';
const browser=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const ctx=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2,locale:'fr-FR'});
const p=await ctx.newPage();
await p.goto(B+'/login');
await p.fill('form[action$="/login"] [name=email]','proprietaire@demo.jackcie.test');
await p.fill('form[action$="/login"] [name=password]','Demo2026!demo');
await p.click('form[action$="/login"] button.btn-primary');
await p.waitForLoadState('networkidle');
await ctx.storageState({path:OUT+'state.json'});
console.log('after login',p.url());
const pages=[['sante','/sante'],['horse-sante','/chevaux/1/sante'],['soin-nouveau','/chevaux/1/soins/nouveau'],['poids','/chevaux/1/poids'],['urgence','/chevaux/1/urgence'],['vet','/chevaux/1/resume-veterinaire']];
for(const [n,u] of pages){
  const t0=Date.now();
  const r=await p.goto(B+u); await p.waitForLoadState('networkidle');
  const h=await p.evaluate(()=>document.documentElement.scrollHeight);
  const sw=await p.evaluate(()=>document.documentElement.scrollWidth);
  console.log(n,r.status(),p.url(),'h',h,'sw',sw,Date.now()-t0,'ms');
  await p.screenshot({path:OUT+`m-${n}-top.png`});
  await p.screenshot({path:OUT+`m-${n}-full.png`,fullPage:true});
  // small tap targets
  const small=await p.evaluate(()=>[...document.querySelectorAll('a,button,input,select,label[for],summary')].filter(e=>{const r=e.getBoundingClientRect();const s=getComputedStyle(e);return r.width>0&&r.height>0&&s.visibility!=='hidden'&&(r.height<40||r.width<40)&&!['hidden','checkbox','radio'].includes(e.type)}).slice(0,25).map(e=>{const r=e.getBoundingClientRect();return `${e.tagName}${e.type?'['+e.type+']':''} ${Math.round(r.width)}x${Math.round(r.height)} "${(e.innerText||e.value||e.getAttribute('aria-label')||e.name||'').trim().slice(0,40).replace(/\n/g,' ')}"`}));
  console.log(' small:',small.join(' | '));
}
await browser.close();
