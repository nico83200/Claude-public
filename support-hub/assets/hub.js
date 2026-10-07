/* Console d'assistance NLapps : actualisation en direct, réponses, suggestions IA, notifications. */
(function () {
  const H = window.HUB || {};
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const post = (data) => {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    if (!(data instanceof FormData)) body.set('csrf', H.csrf); else body.set('csrf', H.csrf);
    return fetch('index.php', { method: 'POST', body, headers: { 'X-CSRF': H.csrf } }).then((r) => r.json().catch(() => ({})));
  };

  // ------------------------------------------------------------ Application installable + service worker
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});

  // ------------------------------------------------------------ Boîte de réception
  const msgs = $('[data-msgs]');
  const form = $('[data-reply]');
  const list = $('[data-list]');
  let last = form ? +form.dataset.last : 0, unreadBefore = null;
  const label = { bot: 'Chatbot', user_bot: 'Question au chatbot', user: 'Utilisateur', agent: 'Vous', system: '' };
  const time = (at) => (at.slice(0, 10) === new Date().toISOString().slice(0, 10) ? at.slice(11, 16) : at.slice(8, 10) + '/' + at.slice(5, 7) + ' ' + at.slice(11, 16));
  const add = (m) => {
    const d = document.createElement('div');
    d.className = 'm ' + m.from;
    d.innerHTML = '<div>' + esc(m.text).replace(/\n/g, '<br>') + '</div>'
      + (m.file ? '<a href="index.php?file=' + esc(m.file) + '" target="_blank"><img src="index.php?file=' + esc(m.file) + '" alt="Image jointe"></a>' : '')
      + '<small>' + label[m.from] + ' · ' + time(m.at) + '</small>';
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  };
  if (msgs) msgs.scrollTop = msgs.scrollHeight;
  const beep = () => { try { const a = new AudioContext(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 880; g.gain.value = .08; o.start(); o.stop(a.currentTime + .18); } catch (e) {} };
  const notifyMe = (t, b) => { if (document.hidden && 'Notification' in window && Notification.permission === 'granted') new Notification(t, { body: b, icon: 'assets/icon-192.png' }); };

  async function tick() {
    try {
      const tab = list ? list.dataset.tab : 'open';
      const r = await (await fetch('index.php?feed=1&tab=' + tab + (msgs ? '&c=' + msgs.dataset.c + '&after=' + last : ''), { cache: 'no-store' })).json();
      if (msgs && r.messages) r.messages.forEach((m) => { if (m.id > last) { last = m.id; add(m); if (m.from === 'user') { beep(); notifyMe('Nouveau message', m.text); } } });
      document.title = (r.unread ? '(' + r.unread + ') ' : '') + 'Assistance NLapps';
      if (unreadBefore !== null && r.unread > unreadBefore && !msgs) { beep(); notifyMe('Nouveau message', 'Une conversation attend votre réponse.'); }
      unreadBefore = r.unread;
      if (list && r.list) {
        list.innerHTML = r.list.map((c) => '<a href="index.php?c=' + c.id + '" class="item' + (msgs && +msgs.dataset.c === c.id ? ' sel' : '') + '"><span class="who">' + esc(c.user_name || 'Utilisateur')
          + (c.unread > 0 ? '<i class="badge">' + c.unread + '</i>' : '') + '</span><small>' + esc(c.client) + (c.center ? ' · ' + esc(c.center) : '') + '</small><span class="last">' + esc((c.last || '').slice(0, 70)) + '</span></a>').join('')
          || '<p class="muted pad">Aucune conversation.</p>';
      }
      if (r.counts) document.querySelectorAll('[data-tabs] a').forEach((a) => { const k = a.dataset.tab; const base = a.textContent.replace(/\s*\(\d+\)$/, ''); a.textContent = base + (k !== 'all' && r.counts[k] ? ' (' + r.counts[k] + ')' : ''); });
    } catch (e) {}
  }
  if (list || msgs) setInterval(tick, 4000);
  if ('Notification' in window && Notification.permission === 'default') document.addEventListener('click', () => Notification.requestPermission(), { once: true });

  if (form) {
    const ta = $('textarea', form);
    const first = form.dataset.first || '';
    const fill = (t) => t.replace(/\{prenom\}/g, first || '').replace(/Bonjour\s+,/g, 'Bonjour,');
    const img = $('[data-image]', form);
    const imgName = $('[data-image-name]', form);
    if (img) img.addEventListener('change', () => { imgName.textContent = img.files[0] ? img.files[0].name : ''; });
    ta.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    // Coller une capture d'écran directement dans la zone de réponse
    ta.addEventListener('paste', (e) => {
      const f = [...(e.clipboardData?.files || [])].find((x) => x.type.startsWith('image/'));
      if (f && img) { const dt = new DataTransfer(); dt.items.add(f); img.files = dt.files; imgName.textContent = 'capture collée'; e.preventDefault(); }
    });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = fill(ta.value.trim());
      if (!text && !(img && img.files.length)) return;
      const fd = new FormData(form);
      fd.set('text', text);
      const btn = $('button.primary', form); btn.disabled = true;
      const r = await post(fd);
      btn.disabled = false;
      if (r.error) { alert(r.error); return; }
      ta.value = ''; if (img) { img.value = ''; imgName.textContent = ''; }
      tick();
    });
    const quick = $('[data-quick]', form);
    if (quick) quick.addEventListener('change', () => { if (quick.value) { ta.value = (ta.value ? ta.value + '\n' : '') + fill(quick.value); quick.value = ''; ta.focus(); } });
    const sug = $('[data-suggest]', form);
    if (sug) sug.addEventListener('click', async () => {
      sug.disabled = true; const old = sug.textContent; sug.textContent = '✨ Rédaction…';
      const r = await post({ action: 'ai_suggest', c: msgs.dataset.c });
      sug.disabled = false; sug.textContent = old;
      if (r.error) { alert(r.error); return; }
      ta.value = r.text || ''; ta.focus(); ta.rows = Math.min(10, Math.max(3, ta.value.split('\n').length + 1));
    });
    ta.focus();
  }

  // ------------------------------------------------------------ Notifications push (Réglages)
  const pushBtn = $('[data-push-enable]');
  const pushState = $('[data-push-state]');
  const b64ToBytes = (b) => { const p = '='.repeat((4 - b.length % 4) % 4); const raw = atob((b + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...raw].map((c) => c.charCodeAt(0))); };
  if (pushBtn) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      pushBtn.disabled = true;
      pushState.textContent = 'Non pris en charge par ce navigateur (sur iPhone : ajoutez d\'abord la console à l\'écran d\'accueil).';
    }
    pushBtn.addEventListener('click', async () => {
      try {
        if ((await Notification.requestPermission()) !== 'granted') { pushState.textContent = 'Notifications refusées dans le navigateur.'; return; }
        const reg = await navigator.serviceWorker.ready;
        const sub = (await reg.pushManager.getSubscription()) || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(H.vapid) });
        const ua = navigator.userAgent;
        const labelTxt = (/iPhone|iPad/.test(ua) ? 'iPhone / iPad' : /Android/.test(ua) ? 'Android' : /Mac/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'PC Windows' : 'Appareil') + ' · ' + (/Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : 'navigateur');
        const r = await post({ action: 'push_subscribe', sub: JSON.stringify(sub), label: labelTxt });
        pushState.textContent = r.ok ? 'Activé ✓' : (r.error || 'Échec');
        if (r.ok) setTimeout(() => location.reload(), 800);
      } catch (e) { pushState.textContent = 'Échec de l\'activation : ' + e.message; }
    });
  }

  // ------------------------------------------------------------ QR code de la double authentification
  const qr = $('[data-qr]');
  if (qr) {
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    s.onload = () => new window.QRCode(qr, { text: qr.dataset.qr, width: 180, height: 180, correctLevel: window.QRCode.CorrectLevel.M });
    document.head.appendChild(s);
  }
  // ------------------------------------------------------------ Installation comme application
  let deferred = null;
  const installBtns = [...document.querySelectorAll('[data-install], [data-install-btn]')];
  const installState = $('[data-install-state]');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  if (standalone && installState) installState.textContent = 'Déjà installée sur cet appareil ✓';
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault(); deferred = e;
    installBtns.forEach((b) => { b.hidden = false; b.disabled = false; });
  });
  window.addEventListener('appinstalled', () => { deferred = null; installBtns.forEach((b) => { if (b.dataset.install !== undefined) b.hidden = true; }); if (installState) installState.textContent = 'Installée ✓'; });
  installBtns.forEach((b) => b.addEventListener('click', async () => {
    if (deferred) { deferred.prompt(); const r = await deferred.userChoice; deferred = null; if (installState) installState.textContent = r.outcome === 'accepted' ? 'Installée ✓' : 'Installation annulée'; return; }
    if (installState) installState.textContent = /iPhone|iPad/.test(navigator.userAgent) ? 'Sur iPhone/iPad : Safari → Partager → « Sur l\'écran d\'accueil ».' : (standalone ? 'Déjà installée ✓' : 'Utilisez le menu du navigateur : « Installer l\'application ».');
  }));
})();

