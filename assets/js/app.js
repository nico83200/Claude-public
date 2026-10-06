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
      t && t.classList.toggle('hidden', r.hasAttribute('data-toggle-hide'));
    });
  });
  $$('input[type=file][data-preview]').forEach((inp) => {
    inp.addEventListener('change', () => {
      const img = $(inp.dataset.preview);
      if (img && inp.files[0]) { img.src = URL.createObjectURL(inp.files[0]); img.classList.remove('hidden'); }
    });
  });

  // Fermeture du menu mobile
  document.addEventListener('click', (e) => {
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar') && !e.target.closest('.menu-btn')) {
      document.body.classList.remove('nav-open');
    }
  });
})();
