/* Commandes Centres — interactions */
(function () {
  'use strict';
  const csrf = (window.APP && window.APP.csrf) || '';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const ICONS = {
    check: '<path d="m5 12 5 5 9-10"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    heart: '<path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/>',
    box: '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    sparkles: '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/>',
    alert: '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18h.01"/>',
  };
  const icon = (n, s = 18) => `<svg class="ic" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${ICONS[n] || ICONS.box}</svg>`;

  function toast(msg, isError) {
    const z = $('#toasts');
    if (!z) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = (isError ? icon('alert') : icon('check')) + '<span>' + esc(msg) + '</span>';
    if (isError) t.style.background = '#b91c1c';
    z.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; }, 2600);
    setTimeout(() => t.remove(), 3100);
  }

  async function post(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    if (!(data instanceof FormData)) body.append('_token', csrf);
    const r = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf }, credentials: 'same-origin' });
    return r.json();
  }

  function updateCartCount(n) {
    const c = $('#cart-count');
    if (!c) return;
    c.textContent = n;
    c.style.display = n > 0 ? '' : 'none';
    c.animate([{ transform: 'scale(1.5)' }, { transform: 'scale(1)' }], { duration: 300 });
  }

  // --------------------------------------------------------- Ajout au panier (sans recharger)
  document.addEventListener('submit', async (e) => {
    const f = e.target;
    if (!f.matches('[data-add-cart]')) return;
    e.preventDefault();
    const btn = $('button[type=submit]', f);
    btn && (btn.disabled = true);
    try {
      const res = await post(f.action, new FormData(f));
      if (res.ok) { toast('« ' + res.name + ' » ajouté au panier'); updateCartCount(res.count); }
      else toast(res.error || 'Erreur', true);
    } catch (err) { f.submit(); }
    btn && (btn.disabled = false);
  });

  // --------------------------------------------------------- Favoris
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-fav]');
    if (!b) return;
    e.preventDefault();
    try {
      const res = await post('index.php?r=favorite/toggle', { id: b.dataset.fav });
      $$('[data-fav="' + b.dataset.fav + '"]').forEach((x) => x.classList.toggle('on', !!res.fav));
      toast(res.fav ? 'Ajouté aux favoris' : 'Retiré des favoris');
    } catch (err) { /* ignoré */ }
  });

  // --------------------------------------------------------- Suggestions instantanées
  $$('[data-suggest]').forEach((form) => {
    const input = $('input[type=search]', form);
    if (!input) return;
    let box = null, timer = null, items = [], sel = -1, last = '';
    const close = () => { box && box.remove(); box = null; sel = -1; };
    const render = (data, q) => {
      close();
      if (!data.results || !data.results.length) return;
      box = document.createElement('div');
      box.className = 'suggest';
      box.innerHTML = data.results.map((p) => `
        <a href="${esc(p.url)}">
          <span class="thumb" style="width:38px;height:38px;background:${esc(p.category_color || p.supplier_color || '#8b5cf6')}">${p.image ? `<img src="${esc(p.image)}" alt="">` : icon('box', 18)}</span>
          <span style="flex:1;min-width:0"><span class="strong" style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.name)}</span><small class="muted">${esc(p.supplier)}${p.unit ? ' · ' + esc(p.unit) : ''}</small></span>
          ${p.price ? `<strong class="nowrap">${esc(p.price)}</strong>` : ''}
        </a>`).join('') +
        `<div class="foot">${icon('sparkles', 14)} Appuyez sur Entrée pour tous les résultats${data.total > data.results.length ? ' (' + data.total + ')' : ''} et l'aide de l'assistant</div>`;
      form.style.position = 'relative';
      form.appendChild(box);
      items = $$('a', box);
    };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(async () => {
        if (q === last) return;
        last = q;
        try {
          const r = await fetch('index.php?r=api/search&limit=6&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' } });
          const data = await r.json();
          if (input.value.trim() === q) render(data, q);
        } catch (err) { /* ignoré */ }
      }, 180);
    });
    input.addEventListener('keydown', (e) => {
      if (!box) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach((a, i) => a.classList.toggle('sel', i === sel));
      } else if (e.key === 'Enter' && sel >= 0) {
        e.preventDefault();
        location.href = items[sel].href;
      } else if (e.key === 'Escape') close();
    });
    document.addEventListener('click', (e) => { if (!form.contains(e.target)) close(); });
  });

  // --------------------------------------------------------- Assistant IA
  const ai = $('#ai-panel');
  if (ai) {
    const body = $('.ai-body', ai);
    const q = ai.dataset.query;
    const card = (p) => `
      <article class="product">
        <div class="product-img" style="${p.image ? '' : `background:linear-gradient(135deg,${esc(p.category_color || '#8b5cf6')},${esc(p.supplier_color || '#6366f1')})`}">
          ${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.name)}" loading="lazy">` : `<div class="product-ph">${icon('box', 56)}</div>`}
          ${p.category ? `<span class="badge product-cat" style="background:rgba(255,255,255,.92);color:#333">${esc(p.category)}</span>` : ''}
          <button type="button" class="product-fav ${p.fav ? 'on' : ''}" data-fav="${p.id}" title="Favori">${icon('heart', 17)}</button>
        </div>
        <div class="product-body">
          <a class="product-name" href="${esc(p.url)}">${esc(p.name)}</a>
          <div class="product-meta">${esc(p.supplier)}${p.reference ? ' · Réf. ' + esc(p.reference) : ''}</div>
          ${p.unit ? `<div class="product-meta">${esc(p.unit)}</div>` : ''}
          <div class="spacer"></div>
          ${p.price ? `<div class="product-price">${esc(p.price)}${p.catalog_price ? `<s>${esc(p.catalog_price)}</s>` : ''} <small class="muted" style="font-weight:500;font-size:.75rem">HT</small></div>` : ''}
        </div>
        <form class="product-foot" method="post" action="index.php?r=cart/add" data-add-cart>
          <input type="hidden" name="_token" value="${esc(csrf)}">
          <input type="hidden" name="product_id" value="${p.id}">
          <input class="qty-input" type="number" name="qty" min="1" max="9999" value="${Math.max(1, p.min_qty || 1)}">
          <button class="btn btn-primary btn-sm" type="submit">${icon('plus', 16)} Ajouter</button>
        </form>
      </article>`;
    fetch('index.php?r=api/ai-search&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' } })
      .then((r) => r.json())
      .then((d) => {
        if (!d.available) { ai.remove(); return; }
        if (d.error) { body.innerHTML = `<p class="ai-msg muted">${esc(d.error)}</p>`; return; }
        let html = `<p class="ai-msg">${esc(d.message || '')}</p>`;
        if (d.results && d.results.length) html += `<div class="products">${d.results.map(card).join('')}</div>`;
        if (d.related && d.related.length) {
          html += `<div class="chips mt-2"><small class="muted" style="align-self:center">Voir aussi :</small>${d.related.map((r) => `<a class="chip" href="index.php?r=catalog&q=${encodeURIComponent(r)}">${icon('search', 14)} ${esc(r)}</a>`).join('')}</div>`;
        }
        body.innerHTML = html;
      })
      .catch(() => { body.innerHTML = '<p class="ai-msg muted">Assistant indisponible pour le moment.</p>'; });
  }

  // --------------------------------------------------------- Réception : cases ↔ quantités
  const recv = $('#recv-form');
  if (recv) {
    $$('[data-line]', recv).forEach((line) => {
      const cb = $('input[type=checkbox]', line);
      const qty = $('input[type=number]', line);
      const full = parseInt(cb.dataset.full, 10);
      const sync = () => line.classList.toggle('ok', parseInt(qty.value || '0', 10) >= full);
      cb.addEventListener('change', () => { qty.value = cb.checked ? full : 0; sync(); });
      qty.addEventListener('input', () => { cb.checked = parseInt(qty.value || '0', 10) >= full; sync(); });
    });
    const all = $('[data-check-all]', recv);
    all && all.addEventListener('click', () => {
      $$('[data-line]', recv).forEach((line) => {
        const cb = $('input[type=checkbox]', line);
        cb.checked = true;
        cb.dispatchEvent(new Event('change'));
      });
    });
  }

  // --------------------------------------------------------- Demandes à traiter : sélection
  $$('[data-po-group]').forEach((f) => {
    const boxes = $$('input[name="lines[]"]', f);
    const total = $('[data-sel-total]', f);
    const fmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
    const refresh = () => {
      const sum = boxes.filter((b) => b.checked).reduce((s, b) => s + parseFloat(b.dataset.amount || 0), 0);
      total && (total.textContent = fmt.format(sum));
    };
    const ta = $('[data-toggle-all]', f);
    ta && ta.addEventListener('change', () => { boxes.forEach((b) => (b.checked = ta.checked)); refresh(); });
    boxes.forEach((b) => b.addEventListener('change', refresh));
  });

  document.addEventListener('submit', (e) => {
    const f = e.target;
    const btn = e.submitter;
    if (btn && btn.dataset.confirm && !confirm(btn.dataset.confirm)) { e.preventDefault(); return; }
    if (f.matches('[data-refuse]')) {
      const reason = prompt('Motif du refus (visible par le demandeur) :', 'Article non validé par le service achats');
      if (reason === null) { e.preventDefault(); return; }
      $('input[name=reason]', f).value = reason;
    }
  }, true);

  // --------------------------------------------------------- Divers formulaires
  $$('[data-toggle-target]').forEach((r) => {
    r.addEventListener('change', () => {
      const t = $(r.dataset.toggleTarget);
      const hide = r.type === 'checkbox' ? (r.hasAttribute('data-invert') ? r.checked : !r.checked) : r.hasAttribute('data-toggle-hide');
      t && t.classList.toggle('hidden', hide);
    });
  });
  $$('input[type=file][data-preview]').forEach((inp) => {
    inp.addEventListener('change', () => {
      const img = $(inp.dataset.preview);
      if (img && inp.files[0]) { img.src = URL.createObjectURL(inp.files[0]); img.classList.remove('hidden'); const em = document.getElementById('logo-empty'); em && em.remove(); }
    });
  });

  // Fermeture du menu mobile
  document.addEventListener('click', (e) => {
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar') && !e.target.closest('.menu-btn')) {
      document.body.classList.remove('nav-open');
    }
  });
})();

/* =====================================================================
   v1.1 — Scanner de codes-barres, notifications, inventaire, mises à jour
   ===================================================================== */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (msg, err) => {
    const z = $('#toasts'); if (!z) return;
    const t = document.createElement('div'); t.className = 'toast'; if (err) t.style.background = '#b91c1c';
    t.textContent = msg; z.appendChild(t); setTimeout(() => t.remove(), 3200);
  };

  // --------------------------------------------------------- Scanner caméra
  let zxingLoading = null;
  function loadZXing() {
    if (window.ZXing) return Promise.resolve(window.ZXing);
    zxingLoading = zxingLoading || new Promise((res, rej) => {
      const s = document.createElement('script');
      s.src = 'assets/vendor/zxing/zxing.min.js';
      s.onload = () => res(window.ZXing); s.onerror = rej;
      document.head.appendChild(s);
    });
    return zxingLoading;
  }

  function beep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator(); const g = ctx.createGain();
      o.frequency.value = 1150; o.connect(g); g.connect(ctx.destination); g.gain.value = 0.08;
      o.start(); setTimeout(() => { o.stop(); ctx.close(); }, 120);
    } catch (e) { /* ignoré */ }
    if (navigator.vibrate) navigator.vibrate(80);
  }

  /** Ouvre le scanner ; onCode(code) est appelé avec le code lu (ou saisi). */
  window.openScanner = function (onCode, title) {
    const ov = document.createElement('div');
    ov.className = 'scanner';
    ov.innerHTML = `
      <h3>📷 ${esc(title || 'Scanner un code-barres')}</h3>
      <div class="scanner-box"><video playsinline muted></video><div class="scanner-aim"></div></div>
      <div class="scanner-status">Placez le code-barres dans le cadre…</div>
      <form class="scanner-bar">
        <input type="text" inputmode="numeric" placeholder="ou saisissez le code (douchette, clavier)" autocomplete="off">
        <button class="btn btn-primary" type="submit">Valider</button>
        <button class="btn" type="button" data-close>Fermer</button>
      </form>`;
    document.body.appendChild(ov);
    const video = $('video', ov), status = $('.scanner-status', ov), input = $('input', ov);
    let stream = null, done = false, timer = null;

    const stop = () => {
      done = true; clearTimeout(timer);
      if (stream) stream.getTracks().forEach((t) => t.stop());
      ov.remove();
    };
    const found = (code) => {
      if (done || !code) return;
      beep(); stop(); onCode(String(code).trim());
    };
    $('[data-close]', ov).addEventListener('click', stop);
    ov.addEventListener('keydown', (e) => { if (e.key === 'Escape') stop(); });
    $('form', ov).addEventListener('submit', (e) => { e.preventDefault(); found(input.value); });

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      status.innerHTML = 'Caméra indisponible : l\'application doit être ouverte en <strong>HTTPS</strong>. Vous pouvez saisir le code ci-dessous.';
      input.focus();
      return;
    }
    const constraints = { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false };
    const formats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code', 'data_matrix'];

    // Capture d'une image de la vidéo toutes les 200 ms puis décodage
    const canvas = document.createElement('canvas');
    const ctx2d = canvas.getContext('2d', { willReadFrequently: true });
    const startCamera = async () => {
      stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = stream;
      await video.play();
    };
    const loop = (decodeFrame) => {
      const tick = async () => {
        if (done) return;
        if (video.readyState >= 2 && video.videoWidth) {
          try {
            const code = await decodeFrame();
            if (code) return found(code);
          } catch (e) { /* aucun code sur cette image */ }
        }
        timer = setTimeout(tick, 200);
      };
      tick();
    };
    const useNative = async () => {
      const supported = await window.BarcodeDetector.getSupportedFormats();
      const det = new window.BarcodeDetector({ formats: formats.filter((f) => supported.includes(f)) });
      await startCamera();
      loop(async () => { const codes = await det.detect(video); return codes.length ? codes[0].rawValue : null; });
    };
    const useZXing = async () => {
      const ZX = await loadZXing();
      const hints = new Map();
      hints.set(ZX.DecodeHintType.TRY_HARDER, true);
      const mfr = new ZX.MultiFormatReader();
      mfr.setHints(hints);
      await startCamera();
      loop(async () => {
        // Le décodage sur une image réduite reste rapide sur les tablettes modestes
        const scale = Math.min(1, 960 / video.videoWidth);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);
        const bmp = new ZX.BinaryBitmap(new ZX.HybridBinarizer(new ZX.HTMLCanvasElementLuminanceSource(canvas)));
        try { return mfr.decodeWithState(bmp).getText(); } finally { mfr.reset(); }
      });
    };
    (async () => {
      try {
        if ('BarcodeDetector' in window) {
          try { await useNative(); return; } catch (e) { if (stream) stream.getTracks().forEach((t) => t.stop()); stream = null; }
        }
        await useZXing();
      } catch (e) {
        status.innerHTML = e && e.name === 'NotAllowedError'
          ? 'Accès à la caméra refusé. Autorisez la caméra pour ce site dans les réglages du navigateur, ou saisissez le code.'
          : 'Impossible de démarrer la caméra (' + esc(e && e.message || e) + '). Saisissez le code ci-dessous.';
        input.focus();
      }
    })();
  };

  const suggestUrl = (code) => 'index.php?r=suggest&from=scan&barcode=' + encodeURIComponent(code);
  function offerSuggest(code) {
    if (confirm('Le code ' + code + ' ne correspond à aucun article du catalogue.\n\nVoulez-vous proposer cet article au service achats ?')) location.href = suggestUrl(code);
  }

  async function lookup(code) {
    const r = await fetch('index.php?r=api/barcode&code=' + encodeURIComponent(code), { headers: { 'X-Requested-With': 'fetch' } });
    return r.json();
  }

  // Actions des boutons [data-scan]
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-scan]');
    if (!b) return;
    e.preventDefault();
    const mode = b.dataset.scan;
    if (mode === 'search') {
      openScanner(async (code) => {
        const d = await lookup(code);
        if (d.found) location.href = d.product.url;
        else location.href = suggestUrl(code);
      }, 'Rechercher un article');
    } else if (mode.startsWith('fill:')) {
      openScanner((code) => { const i = $(mode.slice(5)); if (i) { i.value = code; i.dispatchEvent(new Event('input')); } }, 'Lire le code-barres de l\'article');
    } else if (mode.startsWith('fill-select:')) {
      openScanner(async (code) => {
        const sel = $(mode.slice(12)); const d = await lookup(code);
        if (d.found && sel && sel.querySelector('option[value="' + d.product.id + '"]')) { sel.value = d.product.id; toast(d.product.name); }
        else if (d.found) toast('« ' + d.product.name + ' » n\'est pas dans cette liste.', true);
        else offerSuggest(code);
      }, 'Scanner l\'article');
    } else if (mode === 'stock') {
      openScanner(async (code) => {
        const d = await lookup(code);
        if (!d.found) { offerSuggest(code); return; }
        const row = $('[data-stock-row="' + d.product.id + '"]');
        if (row) {
          row.scrollIntoView({ behavior: 'smooth', block: 'center' });
          row.classList.remove('flash-row'); void row.offsetWidth; row.classList.add('flash-row');
          const inp = $('.count-input', row); if (inp) setTimeout(() => inp.focus(), 350);
          toast(d.product.name + ' — en stock : ' + (d.stock ? d.stock.qty : 0));
        } else {
          const sel = $('#add-product');
          if (sel && sel.querySelector('option[value="' + d.product.id + '"]')) {
            sel.value = d.product.id; sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
            toast('« ' + d.product.name + ' » n\'est pas encore suivi : indiquez son stock puis ajoutez-le.');
          } else toast(d.product.name, true);
        }
      }, 'Inventaire : scanner un article');
    }
  });

  // --------------------------------------------------------- Inventaire
  $$('.count-input').forEach((i) => i.addEventListener('input', () => {
    i.classList.toggle('changed', i.value !== '' && i.value !== i.dataset.current);
  }));
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-exit]');
    if (!b) return;
    const sel = $('#exit-product');
    if (sel) { sel.value = b.dataset.exit; const f = $('#exit-form'); f.scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(() => $('input[name=qty]', f).select(), 300); }
  });
  const sf = $('#stock-form');
  sf && window.addEventListener('beforeunload', (e) => {
    if (!sf.dataset.submitting && $$('.count-input.changed', sf).length) { e.preventDefault(); e.returnValue = ''; }
  });
  sf && sf.addEventListener('submit', () => { sf.dataset.submitting = '1'; });

  // --------------------------------------------------------- Cloche de notifications
  const bell = $('[data-bell]');
  if (bell) {
    let pop = null;
    bell.addEventListener('click', async (e) => {
      if (window.innerWidth < 600) return; // sur mobile : page complète
      e.preventDefault();
      if (pop) { pop.remove(); pop = null; return; }
      const d = await (await fetch('index.php?r=api/notifications', { headers: { 'X-Requested-With': 'fetch' } })).json();
      pop = document.createElement('div');
      pop.className = 'notif-pop';
      pop.innerHTML = (d.items.length ? d.items.map((n) => `<a class="item ${n.read ? '' : 'unread'}" href="${esc(n.url)}"><div class="strong">${esc(n.title)}</div>${n.body ? `<small>${esc(n.body)}</small><br>` : ''}<small class="muted">${esc(n.when)}</small></a>`).join('')
        : '<div class="empty" style="padding:1.5rem">Aucune notification.</div>') + '<a class="foot" href="index.php?r=notifications">Tout voir</a>';
      bell.parentNode.appendChild(pop);
    });
    document.addEventListener('click', (e) => { if (pop && !e.target.closest('.bell-wrap')) { pop.remove(); pop = null; } });
    // Rafraîchissement du compteur toutes les 2 minutes
    setInterval(async () => {
      try {
        const d = await (await fetch('index.php?r=api/notifications', { headers: { 'X-Requested-With': 'fetch' } })).json();
        const c = $('.bell-count', bell); c.textContent = d.unread; c.style.display = d.unread ? '' : 'none';
      } catch (e) { /* ignoré */ }
    }, 120000);
  }

  // --------------------------------------------------------- Retour arrière (mises à jour)
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-rollback]');
    if (!b) return;
    const f = $('#rollback-form');
    $('input[name=file]', f).value = b.dataset.rollback;
    $('[data-rb-version]', f).textContent = 'v' + b.dataset.version;
    f.classList.remove('hidden');
    f.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
})();

/* =====================================================================
   v1.3 — Photos allégées, application installable, mode réserve
   ===================================================================== */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrf = (window.APP && window.APP.csrf) || '';
  const toast = (msg, err) => {
    const z = $('#toasts'); if (!z) return;
    const t = document.createElement('div'); t.className = 'toast'; if (err) t.style.background = '#b91c1c';
    t.textContent = msg; z.appendChild(t); setTimeout(() => t.remove(), 3200);
  };

  // --------------------------------------------------------- Réduction des photos avant envoi
  // Une photo de smartphone (3 à 8 Mo) est ramenée à 1600 px / ~300 Ko : envoi rapide et sous la limite du serveur.
  async function shrink(file, max = 1600, quality = 0.85) {
    if (!file || !/^image\/(jpeg|png|webp|heic|heif)/i.test(file.type) || (file.size < 700 * 1024)) return file;
    const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' }).catch(() => null);
    if (!bmp) return file;
    const ratio = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * ratio); c.height = Math.round(bmp.height * ratio);
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', quality));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
  }
  document.addEventListener('change', async (e) => {
    const inp = e.target;
    if (!(inp instanceof HTMLInputElement) || inp.type !== 'file' || !inp.files.length || inp.dataset.shrunk === '1') return;
    if (!/image/.test(inp.accept || '') || inp.hasAttribute('data-no-shrink')) return;
    const f = inp.files[0];
    if (!f.type.startsWith('image/')) return;
    const form = inp.form; const btns = form ? $$('button[type=submit]', form) : [];
    btns.forEach((b) => (b.disabled = true));
    try {
      const small = await shrink(f);
      if (small !== f && window.DataTransfer) {
        const dt = new DataTransfer(); dt.items.add(small);
        inp.dataset.shrunk = '1'; inp.files = dt.files; inp.dataset.shrunk = '';
      }
    } catch (err) { /* on garde l'original */ }
    btns.forEach((b) => (b.disabled = false));
  }, true);

  // --------------------------------------------------------- Application installable (PWA)
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
  }
  let deferred = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault(); deferred = e;
    $$('[data-install]').forEach((b) => b.classList.remove('hidden'));
  });
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-install]');
    if (!b || !deferred) return;
    deferred.prompt();
    await deferred.userChoice.catch(() => null);
    deferred = null; b.classList.add('hidden');
  });

  // --------------------------------------------------------- Restauration d'une sauvegarde quotidienne
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-restore-db]');
    if (!b) return;
    const f = $('#restore-db-form');
    $('input[name=file]', f).value = b.dataset.restoreDb;
    $('[data-restore-name]', f).textContent = '(' + b.dataset.restoreDb + ')';
    f.classList.remove('hidden'); f.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  // --------------------------------------------------------- Mode réserve (tablette)
  const q = $('#quick');
  if (q) {
    let mode = 'out', product = null;
    const card = $('[data-quick-card]', q), qty = $('[data-q-qty]', q), go = $('[data-q-go]', q), log = $('[data-quick-log]', q);
    const labels = { out: 'Valider la sortie', in: 'Valider l\'entrée', count: 'Enregistrer le stock compté' };
    const setMode = (m) => {
      mode = m;
      $$('[data-mode]', q).forEach((b) => b.classList.toggle('active', b.dataset.mode === m));
      go.textContent = labels[m]; go.className = 'quick-go quick-' + m;
      if (product) qty.value = m === 'count' ? (product.stock ?? 0) : 1;
    };
    $$('[data-mode]', q).forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));
    $$('[data-step]', q).forEach((b) => b.addEventListener('click', () => { qty.value = Math.max(0, (parseInt(qty.value || '0', 10) + parseInt(b.dataset.step, 10))); }));
    const show = (d) => {
      product = { id: d.product.id, name: d.product.name, stock: d.stock ? d.stock.qty : 0 };
      $('[data-q-name]', q).textContent = d.product.name;
      $('[data-q-meta]', q).textContent = [d.product.unit, d.product.supplier].filter(Boolean).join(' · ');
      $('[data-q-stock]', q).textContent = d.stock ? d.stock.qty : 'non suivi';
      $('[data-q-img]', q).innerHTML = d.product.image ? '<img src="' + esc(d.product.image) + '" alt="">' : '';
      card.classList.remove('hidden');
      setMode(mode);
      qty.focus(); qty.select();
    };
    const lookup = async (code) => {
      const d = await (await fetch('index.php?r=api/barcode&code=' + encodeURIComponent(code), { headers: { 'X-Requested-With': 'fetch' } })).json();
      if (d.found) show(d);
      else if (confirm('Code ' + code + ' inconnu au catalogue.\nProposer cet article au service achats ?')) location.href = 'index.php?r=suggest&from=scan&barcode=' + encodeURIComponent(code);
    };
    $('[data-quick-scan]', q).addEventListener('click', () => window.openScanner && window.openScanner(lookup, 'Mode réserve : scanner l\'article'));
    $('[data-quick-search]', q).addEventListener('submit', (e) => { e.preventDefault(); const v = $('input', e.target).value.trim(); if (v) lookup(v); });
    go.addEventListener('click', async () => {
      if (!product) return;
      const n = Math.max(0, parseInt(qty.value || '0', 10));
      if (mode !== 'count' && n <= 0) return toast('Indiquez une quantité.', true);
      const body = new URLSearchParams({ _token: csrf, product_id: product.id, qty: n, note: $('[data-q-note]', q).value });
      let url = 'index.php?r=stock/exit';
      if (mode === 'in') body.append('mode', 'in');
      if (mode === 'count') url = 'index.php?r=stock/count-one';
      go.disabled = true;
      try {
        const r = await (await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } })).json();
        if (!r.ok) throw new Error(r.error || 'Erreur');
        const verb = { out: 'Sortie de ' + n, in: 'Entrée de ' + n, count: 'Stock compté : ' + n }[mode];
        const li = document.createElement('li');
        li.innerHTML = '<strong>' + esc(product.name) + '</strong> — ' + esc(verb) + ' · reste <strong>' + r.qty + '</strong>';
        log.prepend(li);
        if (navigator.vibrate) navigator.vibrate(60);
        toast(mode === 'count' ? 'Inventaire enregistré : ' + n : verb + ' enregistrée');
        card.classList.add('hidden'); product = null; $('[data-q-note]', q).value = '';
      } catch (err) { toast(err.message, true); }
      go.disabled = false;
    });
  }
})();

/* =====================================================================
   v1.4 — Fiche centre : contrôle des SIREN / SIRET / TVA pendant la saisie
   ===================================================================== */
(function () {
  'use strict';
  const siret = document.getElementById('siret');
  if (!siret) return;
  const siren = document.getElementById('siren');
  const vat = document.getElementById('vat_number');
  const digits = (s) => (s || '').replace(/\D+/g, '');
  const luhn = (d) => { let sum = 0, alt = false; for (let i = d.length - 1; i >= 0; i--) { let n = +d[i]; if (alt) { n *= 2; if (n > 9) n -= 9; } sum += n; alt = !alt; } return sum % 10 === 0; };
  const vatFrom = (s) => 'FR' + String((12 + 3 * (Number(s) % 97)) % 97).padStart(2, '0') + s;
  const msg = (k, ok, text) => { const m = document.querySelector('[data-legal-msg="' + k + '"]'); if (m) { m.textContent = text; m.style.color = ok ? 'var(--green)' : 'var(--red)'; } };
  const checkSiret = () => {
    const d = digits(siret.value);
    if (!d) return msg('siret', true, '');
    if (d.length !== 14) return msg('siret', false, d.length + ' chiffres sur 14');
    const ok = (d.startsWith('356000000') && [...d].reduce((a, c) => a + +c, 0) % 5 === 0) || luhn(d);
    msg('siret', ok, ok ? '✓ SIRET valide' : '✗ clé de contrôle incorrecte');
    if (ok && !digits(siren.value)) { siren.value = d.slice(0, 9); checkSiren(); }
  };
  const checkSiren = () => {
    const d = digits(siren.value);
    if (!d) return msg('siren', true, '');
    if (d.length !== 9) return msg('siren', false, d.length + ' chiffres sur 9');
    const ok = luhn(d);
    msg('siren', ok, ok ? '✓ SIREN valide' : '✗ clé de contrôle incorrecte');
    if (ok && !vat.value) vat.value = vatFrom(d);
  };
  siret.addEventListener('input', checkSiret);
  siren.addEventListener('input', checkSiren);
  checkSiret(); checkSiren();
  document.querySelector('[data-vat-from-siren]').addEventListener('click', () => {
    const d = digits(siren.value);
    if (d.length === 9 && luhn(d)) vat.value = vatFrom(d); else msg('siren', false, 'Saisissez d\'abord un SIREN valide');
  });
})();

// Sélection multiple (cases à cocher) avec barre d'actions groupées
(function () {
  document.querySelectorAll('[data-check-all]').forEach((all) => {
    const form = all.form || all.closest('form');
    if (!form) return;
    const name = all.dataset.checkAll;
    const boxes = () => Array.from(form.elements).filter((b) => b.type === 'checkbox' && b.name === name);
    const bar = form.querySelector('.bulk-bar');
    const refresh = () => {
      const n = boxes().filter((b) => b.checked).length;
      if (bar) { bar.hidden = n === 0; const c = bar.querySelector('[data-bulk-count]'); if (c) c.textContent = n; }
      all.checked = n > 0 && n === boxes().length;
      all.indeterminate = n > 0 && n < boxes().length;
    };
    all.addEventListener('change', () => { boxes().forEach((b) => { b.checked = all.checked; }); refresh(); });
    document.addEventListener('change', (e) => { if (e.target.form === form && e.target.name === name) refresh(); });
    refresh();
  });
})();

// Formulaires longs (analyse IA, import) : indicateur d'attente
document.addEventListener('submit', (e) => {
  const f = e.target.closest('form[data-busy]');
  if (!f || e.defaultPrevented) return;
  const o = document.createElement('div');
  o.className = 'busy-overlay';
  o.innerHTML = '<div><i></i><span></span></div>';
  o.querySelector('span').textContent = f.dataset.busy;
  setTimeout(() => document.body.appendChild(o), 150);
});

// Import : exemples de valeurs de la colonne choisie
document.querySelectorAll('[data-map-select]').forEach((sel) => {
  sel.addEventListener('change', () => {
    const cell = sel.closest('tr').querySelector('[data-samples]');
    if (cell) cell.textContent = (window.IMPORT_SAMPLES || {})[sel.value] || '';
  });
});

// Copie dans le presse-papiers (n° client, références à coller sur le site du fournisseur)
document.addEventListener('click', async (e) => {
  const b = e.target.closest('[data-copy]');
  if (!b) return;
  try {
    await navigator.clipboard.writeText(b.dataset.copy);
    const old = b.innerHTML; b.textContent = 'Copié ✓'; setTimeout(() => { b.innerHTML = old; }, 1500);
  } catch (err) { window.prompt('Copiez le texte :', b.dataset.copy); }
});

// Fiche fournisseur : champs propres au mode de commande choisi
(function () {
  const radios = document.querySelectorAll('[data-method-radio]');
  if (!radios.length) return;
  const sync = () => {
    const v = (document.querySelector('[data-method-radio]:checked') || {}).value;
    document.querySelectorAll('[data-method-show]').forEach((el) => { el.hidden = el.dataset.methodShow !== v; });
  };
  radios.forEach((r) => r.addEventListener('change', sync));
  sync();
})();
