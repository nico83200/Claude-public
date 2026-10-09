/* Approvia — interactions */
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
    // Réception par scan : chaque code lu ajoute une unité à la ligne correspondante
    const onRecvCode = (code) => {
      code = String(code || '').replace(/\s+/g, '');
      if (!code) return;
      const line = $$('[data-line]', recv).find((l) => (l.dataset.codes || '').split('|').some((c) => c && c.replace(/\s+/g, '') === code));
      if (!line) { toast('Code ' + code + ' : article absent de ce bon de commande.', true); return; }
      const qty = $('input[type=number]', line), cb = $('input[type=checkbox]', line);
      const max = parseInt(qty.max || '0', 10), cur = parseInt(qty.value || '0', 10);
      line.scrollIntoView({ behavior: 'smooth', block: 'center' });
      line.classList.remove('flash-row'); void line.offsetWidth; line.classList.add('flash-row');
      if (cur >= max) { toast(line.dataset.label + ' : déjà ' + max + '/' + max + ' (complet).', true); return; }
      qty.value = cur + 1; qty.dispatchEvent(new Event('input'));
      toast(line.dataset.label + ' : ' + (cur + 1) + '/' + max);
    };
    const scanBtn = $('[data-recv-scan]', recv);
    scanBtn && scanBtn.addEventListener('click', () => window.openScanner && window.openScanner(onRecvCode, 'Réception : scannez chaque article livré', { continuous: true }));
    const codeIn = $('[data-recv-code]', recv);
    codeIn && codeIn.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); onRecvCode(codeIn.value); codeIn.value = ''; } });
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
  window.openScanner = function (onCode, title, opts) {
    opts = opts || {};
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
    let lastCode = '', lastAt = 0;
    const found = (code) => {
      if (done || !code) return;
      code = String(code).trim();
      if (opts.continuous) {
        // Mode continu (réception) : on garde la caméra ouverte ; un code resté sous la caméra n'est compté qu'une fois,
        // il doit quitter le cadre (1,2 s) avant d'être compté à nouveau (boîte suivante du même article)
        const seen = code === lastCode && Date.now() - lastAt < 1200;
        lastCode = code; lastAt = Date.now();
        if (seen) return;
        beep(); onCode(code);
        status.textContent = '✓ ' + code + ' — scannez l\'article suivant (Fermer quand c\'est fini)';
        input.value = '';
        return;
      }
      beep(); stop(); onCode(code);
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
            if (code) { found(code); if (done) return; }
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
          toast(d.product.name + ' — en stock : ' + (d.stock ? d.stock.qty : 0) + (d.stock && d.stock.location ? ' · rangé : ' + d.stock.location : ''));
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
    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js?v=' + ((window.APP && window.APP.version) || '1'), { updateViaCache: 'none' }).then((r) => r.update()).catch(() => {}));
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

  // --------------------------------------------------------- Inventaire tablette : stock actuel uniquement
  const q = $('#quick');
  if (q) {
    let product = null, viaCamera = false, busy = false, lastCode = '', lastAt = 0;
    const card = $('[data-quick-card]', q), qty = $('[data-q-qty]', q), go = $('[data-q-go]', q), log = $('[data-quick-log]', q), diff = $('[data-q-diff]', q);
    const search = $('[data-quick-search] input', q);
    const showDiff = () => {
      if (!product) return;
      const d = Math.max(0, parseInt(qty.value || '0', 10)) - product.stock;
      diff.className = 'quick-diff ' + (d < 0 ? 'out' : d > 0 ? 'in' : '');
      diff.textContent = d < 0 ? 'Sortie calculée : ' + (-d) : d > 0 ? 'Entrée calculée : +' + d : 'Stock conforme';
    };
    $$('[data-step]', q).forEach((b) => b.addEventListener('click', () => { qty.value = Math.max(0, (parseInt(qty.value || '0', 10) + parseInt(b.dataset.step, 10))); showDiff(); }));
    qty.addEventListener('input', showDiff);
    qty.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); go.click(); } });
    const show = (d) => {
      product = { id: d.product.id, name: d.product.name, stock: d.stock ? d.stock.qty : 0, code: d.code || '' };
      $('[data-q-name]', q).textContent = d.product.name;
      $('[data-q-meta]', q).textContent = [d.product.unit, d.product.supplier].filter(Boolean).join(' · ');
      $('[data-q-stock]', q).textContent = d.stock ? d.stock.qty : 'non suivi (0)';
      const ql = $('[data-q-loc]', q); if (ql) { ql.textContent = d.stock && d.stock.location ? '📍 ' + d.stock.location : ''; ql.hidden = !(d.stock && d.stock.location); }
      $('[data-q-img]', q).innerHTML = d.product.image ? '<img src="' + esc(d.product.image) + '" alt="">' : '';
      qty.value = product.stock;
      card.classList.remove('hidden');
      showDiff();
      qty.focus(); qty.select();
    };
    const lookup = async (code) => {
      // Caméra relancée encore pointée sur l'article qu'on vient de valider : on l'ignore et on continue
      if (viaCamera && code === lastCode && Date.now() - lastAt < 5000) { setTimeout(startScan, 300); return; }
      busy = true; card.classList.add('hidden'); product = null;
      let d;
      try { d = await (await fetch('index.php?r=api/barcode&code=' + encodeURIComponent(code), { headers: { 'X-Requested-With': 'fetch' } })).json(); }
      finally { busy = false; }
      if (d.found) { d.code = code; show(d); }
      else if (confirm('Code ' + code + ' inconnu au catalogue.\nProposer cet article au service achats ?')) location.href = 'index.php?r=suggest&from=scan&barcode=' + encodeURIComponent(code);
      else if (viaCamera) startScan();
    };
    const startScan = () => { viaCamera = true; window.openScanner && window.openScanner(lookup, 'Inventaire : scanner l\'article'); };
    $('[data-quick-scan]', q).addEventListener('click', startScan);
    $('[data-quick-search]', q).addEventListener('submit', (e) => { e.preventDefault(); const v = search.value.trim(); if (v) { viaCamera = false; search.value = ''; lookup(v); } });
    go.addEventListener('click', async () => {
      if (!product || go.disabled || busy) return;
      const cur = product; // l'article affiché au moment de la validation
      const n = Math.max(0, parseInt(qty.value || '0', 10));
      const body = new URLSearchParams({ _token: csrf, product_id: cur.id, qty: n });
      go.disabled = true;
      try {
        const r = await (await fetch('index.php?r=stock/count-one', { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } })).json();
        if (!r.ok) throw new Error(r.error || 'Erreur');
        const what = r.delta < 0 ? 'sortie de ' + (-r.delta) : r.delta > 0 ? 'entrée de ' + r.delta : 'conforme';
        const li = document.createElement('li');
        li.innerHTML = '<strong>' + esc(cur.name) + '</strong> — stock <strong>' + r.qty + '</strong> · <span class="' + (r.delta < 0 ? 'out' : r.delta > 0 ? 'in' : '') + '">' + esc(what) + '</span>';
        log.prepend(li);
        if (navigator.vibrate) navigator.vibrate(60);
        toast(cur.name + ' : stock ' + r.qty + ' (' + what + ')');
        lastCode = cur.code; lastAt = Date.now();
        if (product === cur) { card.classList.add('hidden'); product = null; }
        // Article suivant : le scanner se relance (ou la saisie douchette reprend la main)
        if (viaCamera) setTimeout(startScan, 350); else search.focus();
      } catch (err) { toast(err.message, true); }
      go.disabled = false;
    });
    search.focus();
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

// Assistance : chatbot de premier niveau, puis conversation en direct avec l'équipe NLapps (ou formulaire)
(function () {
  const panel = document.querySelector('[data-help-panel]');
  if (!panel) return;
  const fab = document.querySelector('[data-help-open]');
  const log = panel.querySelector('[data-help-log]');
  const form = panel.querySelector('[data-help-form]');
  const input = form.querySelector('input[type=text]');
  const title = panel.querySelector('[data-help-title]'), sub = panel.querySelector('[data-help-sub]');
  const endLink = panel.querySelector('[data-help-end]');
  const attach = panel.querySelector('[data-help-attach]');
  const LIVE = panel.dataset.live === '1', OP = panel.dataset.operator || 'NLapps';
  const token = (window.APP && window.APP.csrf) || '';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const transcript = [];          // échange avec le chatbot, transmis au conseiller
  let mode = 'bot', last = 0, timer = null, shown = new Set();

  const open = () => { panel.hidden = false; fab.hidden = true; setTimeout(() => input.focus(), 50); if (mode === 'live') startPolling(); };
  const close = () => { panel.hidden = true; fab.hidden = false; stopPolling(); };
  fab.addEventListener('click', () => { open(); if (panel.dataset.hasChat === '1' && mode === 'bot') goLive(); });
  panel.querySelector('[data-help-close]').addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });

  const add = (html, who) => { const d = document.createElement('div'); d.className = 'msg ' + who; d.innerHTML = html; log.appendChild(d); log.scrollTop = log.scrollHeight; return d; };
  const actions = (html) => { const a = document.createElement('div'); a.className = 'help-actions'; a.innerHTML = html; log.appendChild(a); log.scrollTop = log.scrollHeight; return a; };
  const offer = (r) => actions((LIVE ? '<button type="button" class="live" data-help-live>👤 Discuter avec un conseiller</button>' : '') + '<a class="form" href="' + esc(r.form || 'index.php?r=support') + '">✉️ Formulaire</a>');

  async function post(url, data) {
    const body = new URLSearchParams(Object.assign({ _token: token }, data));
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
    return res.json();
  }

  // ----- Chatbot
  async function ask(q) {
    add(esc(q), 'me');
    transcript.push({ from: 'user', text: q });
    const t = document.createElement('div'); t.className = 'help-typing'; t.textContent = 'L\'assistant écrit…'; log.appendChild(t);
    let r;
    try { r = await post('index.php?r=api/support', { q, page: document.title + ' (' + location.search + ')' }); }
    catch (err) { r = { answer: 'Connexion impossible pour le moment.', links: [], confident: false, live: LIVE }; }
    t.remove();
    transcript.push({ from: 'bot', text: r.answer });
    let html = esc(r.answer);
    if (r.links && r.links.length) html += '<div class="msg-links">' + r.links.map((l) => '<a href="' + esc(l.url) + '">→ ' + esc(l.label) + '</a>').join('') + '</div>';
    if (r.video) html += '<a class="msg-video" href="' + esc(r.video.url) + '"><svg class="ic" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z" fill="currentColor"/></svg><span>' + esc(r.video.title) + '<small>' + esc(r.video.sub) + '</small></span></a>';
    if (r.others && r.others.length) html += '<small>Voir aussi : ' + r.others.map((o) => '<a href="#" data-help-ask="' + esc(o) + '">' + esc(o) + '</a>').join(' · ') + '</small>';
    add(html, 'bot');
    if (r.human && LIVE) { goLive(q); return; }
    if (r.human || r.source === 'none' || !r.confident) {
      add(LIVE ? 'Un conseiller ' + esc(OP) + ' peut vous répondre directement ici :' : 'Pour une réponse personnalisée, contactez l\'équipe :', 'bot');
      offer(r);
    } else {
      const f = actions('<button type="button" data-ok>👍 C\'est résolu</button><button type="button" data-ko>Ce n\'est pas ça</button>');
      f.querySelector('[data-ok]').addEventListener('click', () => { f.remove(); add('Parfait ! N\'hésitez pas si vous avez une autre question.', 'bot'); });
      f.querySelector('[data-ko]').addEventListener('click', () => { f.remove(); add('Désolé. ' + (LIVE ? 'Un conseiller ' + esc(OP) + ' peut prendre le relais :' : 'L\'équipe peut vous aider directement :'), 'bot'); offer(r); });
    }
  }

  // ----- Conversation en direct
  const setLiveHeader = (av) => {
    title.textContent = 'Conversation avec ' + OP;
    sub.textContent = av && av.online === false ? 'Conseillers absents · réponse dès que possible' : 'Un conseiller vous répond ici';
  };
  const img = (m) => (m.file ? '<a href="index.php?r=api/support/live&action=file&f=' + encodeURIComponent(m.file) + '" target="_blank"><img class="help-img" src="index.php?r=api/support/live&action=file&f=' + encodeURIComponent(m.file) + '" alt="Image"></a>' : '');
  function renderLive(msgs) {
    msgs.forEach((m) => {
      if (shown.has(m.id)) return; shown.add(m.id); last = Math.max(last, m.id);
      if (m.from === 'agent') add(esc(m.text) + img(m) + '<small>' + esc(OP) + ' · ' + esc((m.at || '').slice(11, 16)) + '</small>', 'agent');
      else if (m.from === 'user') add(esc(m.text) + img(m), 'me');
      else add(esc(m.text), 'sys');
    });
  }
  // Note de satisfaction proposée une fois la conversation terminée
  let rated = false;
  function askRating() {
    if (rated || log.querySelector('.help-rate')) return;
    const a = actions('<div class="help-rate"><span>Votre avis sur cette conversation :</span><div class="help-stars">' + [1, 2, 3, 4, 5].map((n) => '<button type="button" data-rate="' + n + '" aria-label="' + n + ' sur 5">★</button>').join('') + '</div></div>');
    a.addEventListener('click', async (e) => {
      const b = e.target.closest('[data-rate]'); if (!b) return;
      rated = true; a.remove();
      const n = +b.dataset.rate;
      add('Merci pour votre note ' + '★'.repeat(n) + '☆'.repeat(5 - n) + ' !', 'sys');
      try { await post('index.php?r=api/support/live', { action: 'rate', rating: n }); } catch (err) {}
    });
  }
  if (attach) attach.querySelector('input').addEventListener('change', async (e) => {
    const f = e.target.files[0]; e.target.value = '';
    if (!f) return;
    if (f.size > 4 * 1024 * 1024) { add('Image trop lourde (4 Mo maximum).', 'sys'); return; }
    const pending = add('📷 Envoi de la capture…', 'me pending');
    const fd = new FormData(); fd.append('_token', token); fd.append('action', 'attach'); fd.append('image', f); fd.append('text', input.value.trim()); fd.append('page', document.title + ' (' + location.search + ')');
    input.value = '';
    let r;
    try { r = await (await fetch('index.php?r=api/support/live', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })).json(); } catch (err) { r = { error: 'Envoi impossible.' }; }
    pending.remove();
    if (r.error) { add(esc(r.error), 'sys'); return; }
    panel.dataset.hasChat = '1'; fab.classList.add('has-chat');
    renderLive(r.messages || []); startPolling();
  });
  async function goLive(firstQuestion) {
    if (!LIVE) { location.href = 'index.php?r=support'; return; }
    if (mode !== 'live') {
      mode = 'live';
      log.querySelectorAll('.help-actions, .help-chips').forEach((x) => x.remove());
      input.placeholder = 'Votre message au conseiller…';
      endLink.hidden = false;
      if (attach) attach.hidden = false;
    }
    let r;
    try { r = await post('index.php?r=api/support/live', { action: 'resume' }); } catch (e) { r = { error: 'Connexion impossible.' }; }
    if (r.error) { add(esc(r.error), 'sys'); return; }
    setLiveHeader(r.availability);
    if (r.rate && !r.chat) askRating();
    if (r.chat) { renderLive(r.messages || []); }
    else {
      const av = r.availability || {};
      add(av.online === false ? esc(av.away_message || 'Nos conseillers sont absents : laissez votre message, nous vous répondrons dès que possible.')
        : 'Écrivez votre message : un conseiller ' + esc(OP) + ' vous répond ici. Votre échange avec l\'assistant lui est transmis.', 'sys');
      if (firstQuestion) { input.value = firstQuestion; input.focus(); }
    }
    startPolling();
  }
  async function sendLive(text) {
    const pending = add(esc(text), 'me pending');
    let r;
    try { r = await post('index.php?r=api/support/live', { action: 'open', text, transcript: JSON.stringify(transcript), page: document.title + ' (' + location.search + ')' }); }
    catch (e) { r = { error: 'Envoi impossible, vérifiez la connexion.' }; }
    pending.remove();
    if (r.error) { add(esc(r.error), 'sys'); input.value = text; return; }
    panel.dataset.hasChat = '1'; fab.classList.add('has-chat');
    renderLive(r.messages || []);
    startPolling();
  }
  async function poll() {
    if (panel.hidden || mode !== 'live') return;
    try {
      const r = await (await fetch('index.php?r=api/support/live&action=poll&after=' + last, { headers: { 'X-Requested-With': 'fetch' } })).json();
      if (r.messages) { const before = last; renderLive(r.messages); if (last > before && r.messages.some((m) => m.from === 'agent')) beep(); }
      if (r.availability) setLiveHeader(r.availability);
      if (r.status === 'closed') { add('Vous pouvez ouvrir une nouvelle conversation en écrivant ci-dessous.', 'sys'); panel.dataset.hasChat = '0'; fab.classList.remove('has-chat'); stopPolling(); if (!r.rated) askRating(); }
    } catch (e) {}
  }
  const beep = () => { try { const a = new AudioContext(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 760; g.gain.value = .05; o.start(); o.stop(a.currentTime + .15); } catch (e) {} };
  function startPolling() { stopPolling(); timer = setInterval(poll, 4000); }
  function stopPolling() { if (timer) clearInterval(timer); timer = null; }

  panel.querySelector('[data-help-close-chat]').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!confirm('Terminer la conversation avec le conseiller ?')) return;
    await post('index.php?r=api/support/live', { action: 'close' });
    stopPolling(); mode = 'bot'; panel.dataset.hasChat = '0'; fab.classList.remove('has-chat'); endLink.hidden = true;
    if (attach) attach.hidden = true;
    title.textContent = 'Assistance Approvia'; input.placeholder = 'Votre question…';
    add('Conversation terminée. Merci ! Le chatbot reste à votre disposition.', 'sys');
    askRating();
  });

  form.addEventListener('submit', (e) => { e.preventDefault(); const q = input.value.trim(); if (!q) return; input.value = ''; mode === 'live' ? sendLive(q) : ask(q); });
  document.addEventListener('click', (e) => {
    const lv = e.target.closest('[data-help-live]');
    if (lv) { e.preventDefault(); open(); goLive(); return; }
    const b = e.target.closest('[data-help-ask]');
    if (b && panel.contains(b)) { e.preventDefault(); ask(b.dataset.helpAsk); }
  });
  if (panel.dataset.autoopen === '1' || (panel.dataset.hasChat === '1' && /[?&]r=support/.test(location.search))) { open(); goLive(); }
})();

// Paramètres : les lignes collées depuis NLapps remplissent l'adresse et la clé du centre d'assistance
(function () {
  const ta = document.querySelector('textarea[name=hub_paste]');
  if (!ta) return;
  ta.addEventListener('input', () => {
    const t = ta.value;
    const url = (t.match(/support_hub_url'?\s*=>\s*'([^']+)'/) || t.match(/https?:\/\/\S+?api\.php/i) || [])[1] || (t.match(/https?:\/\/\S+?api\.php/i) || [])[0];
    const key = (t.match(/support_hub_key'?\s*=>\s*'([^']+)'/) || [])[1] || (t.match(/\bnlh_[a-f0-9]{20,}\b/i) || [])[0];
    if (url) ta.form.querySelector('[name=hub_url]').value = url;
    if (key) { const k = ta.form.querySelector('[name=hub_key]'); k.value = key; k.type = 'text'; setTimeout(() => { k.type = 'password'; }, 1500); }
  });
})();

// Inventaire : seuil conseillé en un clic ; inventaire tournant : écart calculé pendant la saisie
(function () {
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-advised]');
    if (!b) return;
    const inp = b.closest('td').querySelector('input[name^="alert["]');
    if (inp) { inp.value = b.dataset.advised; inp.dispatchEvent(new Event('input')); b.parentElement.remove(); }
  });
  const fmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
  document.querySelectorAll('input[data-expected]').forEach((inp) => {
    const cell = inp.closest('tr').querySelector('[data-gap]');
    inp.addEventListener('input', () => {
      if (inp.value === '') { cell.textContent = ''; cell.className = 'cycle-gap'; return; }
      const g = parseInt(inp.value, 10) - parseInt(inp.dataset.expected, 10);
      cell.className = 'cycle-gap ' + (g > 0 ? 'pos' : g < 0 ? 'neg' : '');
      cell.innerHTML = (g > 0 ? '+' : '') + g + (g ? '<br><small>' + fmt.format(g * parseFloat(inp.dataset.price || 0)) + '</small>' : ' ✓');
    });
  });
})();

// Import : décocher d'un clic les lignes en forte hausse de prix
(function () {
  const b = document.querySelector('[data-uncheck-hikes]');
  if (!b) return;
  b.addEventListener('click', () => {
    document.querySelectorAll('input[data-hike]').forEach((c) => { c.checked = false; c.dispatchEvent(new Event('change', { bubbles: true })); });
    b.textContent = 'Hausses décochées ✓';
  });
})();

// Facture : lecture par l'IA (numéro, date, montant HT) et écarts avec le bon de commande
(function () {
  const btn = document.querySelector('[data-invoice-ai]');
  if (!btn) return;
  const form = btn.closest('form'), out = form.querySelector('[data-invoice-ai-result]');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  btn.addEventListener('click', async () => {
    const fd = new FormData(form);
    const old = btn.innerHTML; btn.disabled = true; btn.textContent = 'Lecture de la facture…';
    out.innerHTML = '';
    let r;
    try { r = await (await fetch(btn.dataset.invoiceAi, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })).json(); }
    catch (e) { r = { error: 'Lecture impossible pour le moment.' }; }
    btn.disabled = false; btn.innerHTML = old;
    if (r.error) { out.innerHTML = '<div class="flash flash-error" style="font-size:.85rem"><div>' + esc(r.error) + '</div></div>'; return; }
    for (const k of ['invoice_number', 'invoice_date', 'invoice_amount']) { const f = form.querySelector('[name=' + k + ']'); if (f && r[k]) { f.value = r[k]; f.classList.add('ai-filled'); } }
    const ok = r.check && r.check.status === 'ok';
    out.innerHTML = '<div class="flash ' + (r.check && !ok ? 'flash-error' : 'flash-success') + '" style="font-size:.85rem"><div><strong>'
      + (r.check ? (ok ? 'Montant conforme aux marchandises reçues' : 'Écart de ' + esc(r.check.diff) + ' (attendu ' + esc(r.check.expected) + ')') : 'Montant HT non lu')
      + '</strong>' + (r.supplier ? '<br><small>Fournisseur lu : ' + esc(r.supplier) + (r.total_ttc ? ' · TTC ' + esc(String(r.total_ttc).replace('.', ',')) + ' €' : '') + '</small>' : '')
      + (r.remarks && r.remarks.length ? '<ul style="margin:.4rem 0 0;padding-left:1.1rem">' + r.remarks.map((x) => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '')
      + '<br><small class="muted">Champs pré-remplis : vérifiez puis « Enregistrer et rapprocher ».</small></div></div>';
  });
})();

// Profil : QR code de configuration de la double authentification
(function () {
  const qr = document.querySelector('[data-qr]');
  if (!qr) return;
  const s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
  s.onload = () => new window.QRCode(qr, { text: qr.dataset.qr, width: 180, height: 180, correctLevel: window.QRCode.CorrectLevel.M });
  document.head.appendChild(s);
})();


/* =====================================================================
   Tutoriels vidéo : chapitres cliquables, départ au bon moment, durée à l'envoi
   ===================================================================== */
(function () {
  'use strict';
  const box = document.querySelector('[data-video-player]');
  if (box) {
    const video = box.querySelector('video');
    const chapters = Array.from(box.querySelectorAll('[data-t]'));
    const start = parseInt(box.dataset.start || '0', 10);
    const go = (t, play) => { video.currentTime = t; if (play) video.play().catch(() => {}); };
    if (start > 0) {
      // Ouverte depuis l'aide sur un chapitre précis : on démarre au bon passage
      video.addEventListener('loadedmetadata', () => go(start, false), { once: true });
      box.scrollIntoView({ block: 'start' });
    }
    chapters.forEach((b) => b.addEventListener('click', () => { go(parseInt(b.dataset.t, 10), true); video.scrollIntoView({ behavior: 'smooth', block: 'center' }); }));
    video.addEventListener('timeupdate', () => {
      let cur = null;
      chapters.forEach((b) => { if (video.currentTime + 0.5 >= parseInt(b.dataset.t, 10)) cur = b; });
      chapters.forEach((b) => b.classList.toggle('on', b === cur));
    });
  }
  // Envoi d'une vidéo : la durée est lue par le navigateur
  const up = document.querySelector('[data-video-upload]');
  if (up) {
    const file = up.querySelector('input[type=file]');
    file.addEventListener('change', () => {
      const f = file.files[0]; if (!f) return;
      const title = up.querySelector('input[name=title]');
      if (title && !title.value) title.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
      const v = document.createElement('video'); v.preload = 'metadata';
      v.onloadedmetadata = () => { up.querySelector('input[name=duration]').value = Math.round(v.duration || 0); URL.revokeObjectURL(v.src); };
      v.src = URL.createObjectURL(f);
    });
  }
})();

/* =====================================================================
   Fenêtre d'accueil : vidéo tutoriel à la connexion, « Ne plus afficher »
   ===================================================================== */
(function () {
  'use strict';
  const box = document.querySelector('[data-welcome-video]');
  if (!box) return;
  const video = box.querySelector('video');
  // Aperçu sur l'écran titre (la première image est un fondu au noir), lecture depuis le tout début
  if (video) {
    let started = false;
    video.addEventListener('loadeddata', () => { if (!started && video.currentTime < 0.1) video.currentTime = 2; }, { once: true });
    video.addEventListener('play', () => { if (!started) { started = true; video.currentTime = 0; } });
  }
  const close = () => {
    const off = box.querySelector('[data-welcome-off]').checked;
    if (video) video.pause();
    box.remove();
    document.removeEventListener('keydown', onKey);
    if (off) fetch('index.php?r=api/welcome-video', { method: 'POST', body: new URLSearchParams({ _token: window.APP.csrf, off: '1' }), headers: { 'X-Requested-With': 'fetch' } }).catch(() => {});
  };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  box.querySelectorAll('[data-welcome-close]').forEach((b) => b.addEventListener('click', close));
  box.addEventListener('click', (e) => { if (e.target === box) close(); });
  document.addEventListener('keydown', onKey);
  // « Tous les tutoriels » : on enregistre aussi le choix avant de quitter la page
  const all = box.querySelector('a[href*="r=videos"]');
  if (all) all.addEventListener('click', () => { if (box.querySelector('[data-welcome-off]').checked) navigator.sendBeacon('index.php?r=api/welcome-video', new URLSearchParams({ _token: window.APP.csrf, off: '1' })); });
})();

// Fenêtres d'information (<dialog>) : bouton [data-dialog-open="id"], fermeture par la croix, Échap ou clic à côté
document.addEventListener('click', (e) => {
  const open = e.target.closest('[data-dialog-open]');
  if (open) {
    const d = document.getElementById(open.dataset.dialogOpen);
    if (d && d.showModal) { e.preventDefault(); d.showModal(); }
    return;
  }
  if (e.target.closest('[data-dialog-close]')) { e.target.closest('dialog').close(); return; }
  if (e.target.tagName === 'DIALOG' && e.target.open) {
    const r = e.target.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
  }
});
