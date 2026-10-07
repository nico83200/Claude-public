/*
 * Widget de conversation NLapps, autonome (sans dépendance) :
 *   <script src="nlapps-chat.js" data-relay="/nlapps/relay.php" data-title="Assistance Mon Appli" defer></script>
 * Le relais (voir examples/relay.php) fait le lien avec le centre d'assistance ; la clé reste sur le serveur.
 */
(function () {
  const me = document.currentScript;
  const RELAY = me.dataset.relay || 'relay.php';
  const TITLE = me.dataset.title || 'Assistance';
  const COLOR = me.dataset.color || '#4f46e5';
  const css = `.nlc-fab{position:fixed;right:18px;bottom:18px;z-index:9999;border:0;border-radius:99px;padding:.75rem 1.1rem;background:${COLOR};color:#fff;font:600 15px system-ui;box-shadow:0 8px 24px rgba(0,0,0,.2);cursor:pointer}
.nlc-panel{position:fixed;right:18px;bottom:18px;z-index:9999;width:min(370px,calc(100vw - 24px));height:min(520px,calc(100vh - 36px));background:#fff;border-radius:16px;box-shadow:0 18px 50px rgba(0,0,0,.25);display:flex;flex-direction:column;font:15px system-ui;color:#0f172a;overflow:hidden}
.nlc-head{background:${COLOR};color:#fff;padding:.8rem 1rem;display:flex;justify-content:space-between;align-items:center}.nlc-head button{background:none;border:0;color:#fff;font-size:1.3rem;cursor:pointer}
.nlc-log{flex:1;overflow-y:auto;padding:.8rem;display:flex;flex-direction:column;gap:.45rem;background:#f8fafc}
.nlc-m{max-width:82%;padding:.5rem .75rem;border-radius:12px;background:#fff;border:1px solid #e2e8f0;white-space:pre-wrap;word-wrap:break-word}.nlc-m.user{align-self:flex-end;background:${COLOR};color:#fff;border:0}.nlc-m.system{align-self:center;background:none;border:0;color:#64748b;font-size:.85rem}
.nlc-in{display:flex;gap:.4rem;padding:.6rem;border-top:1px solid #e2e8f0}.nlc-in input{flex:1;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:10px;font:inherit}.nlc-in button{border:0;border-radius:10px;background:${COLOR};color:#fff;padding:0 .9rem;font:inherit;cursor:pointer}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  const fab = document.createElement('button'); fab.className = 'nlc-fab'; fab.textContent = '💬 ' + TITLE;
  const panel = document.createElement('section'); panel.className = 'nlc-panel'; panel.hidden = true;
  panel.innerHTML = '<div class="nlc-head"><b></b><button type="button" aria-label="Fermer">×</button></div><div class="nlc-log"></div><form class="nlc-in"><input placeholder="Votre message…" maxlength="2000"><button>Envoyer</button></form>';
  panel.querySelector('b').textContent = TITLE;
  document.body.append(fab, panel);
  const log = panel.querySelector('.nlc-log'), form = panel.querySelector('form'), input = form.querySelector('input');
  let last = 0, timer = null; const seen = new Set();
  const add = (m) => { if (seen.has(m.id)) return; seen.add(m.id); last = Math.max(last, m.id); const d = document.createElement('div'); d.className = 'nlc-m ' + m.from; d.textContent = m.text; log.appendChild(d); log.scrollTop = log.scrollHeight; };
  const call = (data) => fetch(RELAY + (data ? '' : '?action=poll&after=' + last), data ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) } : {}).then((r) => r.json());
  async function poll() { try { const r = await call(); (r.messages || []).forEach(add); if (r.chat === false && r.availability && !log.children.length) add({ id: 0, from: 'system', text: r.availability.online ? 'Un conseiller vous répond ici.' : (r.availability.away_message || 'Laissez votre message, nous vous répondons dès que possible.') }); } catch (e) {} }
  fab.onclick = () => { panel.hidden = false; fab.hidden = true; poll(); timer = setInterval(poll, 4000); input.focus(); };
  panel.querySelector('.nlc-head button').onclick = () => { panel.hidden = true; fab.hidden = false; clearInterval(timer); };
  form.onsubmit = async (e) => { e.preventDefault(); const text = input.value.trim(); if (!text) return; input.value = ''; const r = await call({ action: 'send', text, after: last, page: document.title }); if (r.error) add({ id: -Date.now(), from: 'system', text: r.error }); (r.messages || []).forEach(add); };
})();
