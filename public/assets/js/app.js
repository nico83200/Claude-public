/* Crinière Club — interactions boutique (vanilla JS, sans dépendance) */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
// Photo réduite à 2400 px dans le navigateur : envoi rapide en 4G, sans buter sur les limites du serveur
  var shrinkPhoto = function (file) {
    return new Promise(function (resolve) {
      if (!window.createImageBitmap || !/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
      createImageBitmap(file).then(function (bmp) {
        var scale = Math.min(1, 2400 / Math.max(bmp.width, bmp.height));
        if (scale === 1 && file.size < 1.5 * 1024 * 1024) { resolve(file); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(bmp.width * scale);
        c.height = Math.round(bmp.height * scale);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(bmp, 0, 0, c.width, c.height);
        c.toBlob(function (blob) { resolve(blob ? new File([blob], 'cheval.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.9);
      }).catch(function () { resolve(file); });
    });
  };


  var track = function (event, data) {
    if (window.gtag) window.gtag('event', event, data || {});
    if (window.fbq && event === 'add_to_cart') window.fbq('track', 'AddToCart');
  };

  // Menu mobile & recherche ------------------------------------------------
  var toggle = $('.nav-toggle');
  var nav = $('#main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  // Sous-menu « Boutique » : clic (tactile, clavier) en plus du survol
  $$('[data-nav-group]').forEach(function (group) {
    var parent = $('[data-nav-parent]', group);
    parent.addEventListener('click', function () {
      var open = group.classList.toggle('open');
      parent.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!group.contains(e.target) && window.innerWidth > 960) { group.classList.remove('open'); parent.setAttribute('aria-expanded', 'false'); }
    });
    group.addEventListener('keydown', function (e) { if (e.key === 'Escape') { group.classList.remove('open'); parent.setAttribute('aria-expanded', 'false'); parent.focus(); } });
  });
  // Ordinateur : si le menu ne tient pas à côté du logo, il passe sur une seconde ligne (jamais de burger)
  var siteHeader = $('.site-header');
  var fitNav = function () {
    if (!siteHeader || !nav) return;
    siteHeader.classList.remove('is-two-rows');
    if (window.innerWidth > 960 && nav.scrollWidth > nav.clientWidth + 1) siteHeader.classList.add('is-two-rows');
  };
  fitNav();
  window.addEventListener('resize', fitNav);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitNav);
  var searchToggle = $('[data-search-toggle]');
  var searchBar = $('#search-bar');
  if (searchToggle && searchBar) {
    searchToggle.addEventListener('click', function () {
      searchBar.hidden = !searchBar.hidden;
      searchToggle.setAttribute('aria-expanded', searchBar.hidden ? 'false' : 'true');
      if (!searchBar.hidden) $('input', searchBar).focus();
    });
  }

  // Bandeau d'annonces tournant ------------------------------------------
  var announce = $('[data-announcements]');
  if (announce) {
    var messages = JSON.parse(announce.dataset.announcements || '[]');
    var slot = $('[data-announce]', announce);
    var idx = 0;
    if (messages.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setInterval(function () {
        slot.style.opacity = 0;
        setTimeout(function () { idx = (idx + 1) % messages.length; slot.textContent = messages[idx]; slot.style.opacity = 1; }, 400);
      }, 4500);
    }
  }

  // Petits utilitaires de formulaire --------------------------------------
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = el.form || (el.getAttribute('form') && document.getElementById(el.getAttribute('form')));
      if (form) form.submit();
    });
  });
  $$('[data-qty]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentNode.querySelector('input');
      input.value = Math.min(20, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.qty, 10)));
    });
  });

  // Fenêtres modales (guide des tailles, popup) ----------------------------
  var openDialog = function (dialog) {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
  };
  var closeDialog = function (dialog) {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
  };
  $$('[data-open-dialog]').forEach(function (btn) {
    btn.addEventListener('click', function () { openDialog(document.getElementById(btn.dataset.openDialog)); });
  });
  $$('dialog.modal').forEach(function (dialog) {
    $$('[data-modal-close]', dialog).forEach(function (b) { b.addEventListener('click', function () { closeDialog(dialog); }); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDialog(dialog); });
  });

  // Galerie ----------------------------------------------------------------
  var mainImage = $('[data-main-image]');
  // Change l'image principale (srcset = versions optimisées ; data-full = original pour le zoom)
  var setMain = function (src, set, full) {
    if (!mainImage) return;
    if (set) mainImage.srcset = set; else mainImage.removeAttribute('srcset');
    mainImage.src = src;
    mainImage.dataset.full = full || src;
  };
  var aiStage = $('[data-ai-stage]');
  $$('[data-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      setMain(thumb.dataset.thumb, thumb.dataset.thumbSet, thumb.dataset.full);
      var idx = $$('[data-thumb]').indexOf(thumb);
      $$('.gallery-dots span').forEach(function (d, i) { d.classList.toggle('active', i === idx); });
      if (aiStage) aiStage.hidden = true;
      var mangaOverlay = $('[data-manga-print]');
      if (mangaOverlay) { mangaOverlay.hidden = true; mangaOverlay.parentNode.closest('[data-preview-stage]').classList.remove('manga-only'); }
      $$('[data-thumb], [data-ai-show]').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });

  // Galerie : glisser au doigt pour changer de photo ; photo plein écran au toucher / clic
  var galleryStage = mainImage && mainImage.closest('.gallery-main');
  var galleryThumbs = $$('[data-thumb]');
  var currentThumb = function () { return Math.max(0, galleryThumbs.findIndex(function (t) { return t.classList.contains('active'); })); };
  var showThumb = function (i) { var t = galleryThumbs[(i + galleryThumbs.length) % galleryThumbs.length]; if (t) t.click(); return t; };
  if (galleryStage && galleryThumbs.length > 1) {
    var sx = 0, sy = 0, swiping = false;
    galleryStage.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; swiping = true; }, { passive: true });
    galleryStage.addEventListener('touchend', function (e) {
      if (!swiping) return;
      swiping = false;
      var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.3) {
        galleryStage.dataset.swiped = '1';
        showThumb(currentThumb() + (dx < 0 ? 1 : -1));
        setTimeout(function () { delete galleryStage.dataset.swiped; }, 350);
      }
    }, { passive: true });
  }
  var lightbox = $('[data-lightbox]');
  if (galleryStage && lightbox && typeof lightbox.showModal === 'function') {
    var lbImg = $('[data-lightbox-img]', lightbox);
    var lbOpen = function () {
      lbImg.src = mainImage.dataset.full || mainImage.currentSrc || mainImage.src;
      lightbox.classList.remove('is-zoomed');
      lightbox.showModal();
      document.documentElement.style.overflow = 'hidden';
    };
    galleryStage.classList.add('has-lightbox');
    galleryStage.addEventListener('click', function (e) {
      if (galleryStage.dataset.swiped || e.target.closest('button, a, input')) return;
      lbOpen();
    });
    lightbox.addEventListener('close', function () { document.documentElement.style.overflow = ''; });
    $('[data-lightbox-close]', lightbox).addEventListener('click', function () { lightbox.close(); });
    $('[data-lightbox-zoom]', lightbox).addEventListener('click', function () { lightbox.classList.toggle('is-zoomed'); });
    lbImg.addEventListener('dblclick', function () { lightbox.classList.toggle('is-zoomed'); });
    $$('[data-lightbox-step]', lightbox).forEach(function (b) {
      b.addEventListener('click', function () {
        var t = showThumb(currentThumb() + parseInt(b.dataset.lightboxStep, 10));
        if (t) { lbImg.src = t.dataset.full || t.dataset.thumb; lightbox.classList.remove('is-zoomed'); }
      });
    });
    var lx = 0;
    lbImg.addEventListener('touchstart', function (e) { lx = e.touches[0].clientX; }, { passive: true });
    lbImg.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - lx;
      if (!lightbox.classList.contains('is-zoomed') && galleryThumbs.length > 1 && Math.abs(dx) > 50) {
        var t = showThumb(currentThumb() + (dx < 0 ? 1 : -1));
        if (t) lbImg.src = t.dataset.full || t.dataset.thumb;
      }
    }, { passive: true });
  }

  // Zoom au survol de la photo produit (souris uniquement) -------------------
  var zoomStage = mainImage && mainImage.closest('.gallery-main');
  var zoomLayer = zoomStage && $('[data-zoom-layer]', zoomStage);
  if (zoomLayer) {
    var zoomAt = function (e) {
      var r = zoomStage.getBoundingClientRect();
      var x = Math.min(100, Math.max(0, (e.clientX - r.left) / r.width * 100));
      var y = Math.min(100, Math.max(0, (e.clientY - r.top) / r.height * 100));
      zoomLayer.style.transformOrigin = x + '% ' + y + '%';
    };
    zoomStage.addEventListener('pointerenter', function (e) {
      if (e.pointerType !== 'mouse') return;
      // Grossissement selon la définition réelle de l'image (entre ×2,2 et ×3)
      var ratio = mainImage.naturalWidth ? mainImage.naturalWidth / zoomStage.clientWidth : 0;
      zoomStage.style.setProperty('--zoom', Math.min(3, Math.max(2.2, ratio)).toFixed(2));
      zoomAt(e);
      zoomStage.classList.add('is-zooming');
      // Pour le zoom, l'image d'origine (pleine définition) remplace la version optimisée
      var full = mainImage.dataset.full;
      if (full && mainImage.currentSrc !== full && mainImage.getAttribute('srcset')) {
        var hi = new Image();
        hi.onload = function () { mainImage.removeAttribute('srcset'); mainImage.src = full; };
        hi.src = full;
      }
    });
    zoomStage.addEventListener('pointermove', function (e) { if (e.pointerType === 'mouse') zoomAt(e); });
    zoomStage.addEventListener('pointerleave', function () { zoomStage.classList.remove('is-zooming'); });
  }

  // Panier : barre « Commander » fixée en bas sur mobile, masquée quand le récapitulatif est visible
  var paybar = $('[data-cart-paybar]');
  var summary = $('#paiement');
  if (paybar && summary && 'IntersectionObserver' in window) {
    paybar.hidden = false;
    new IntersectionObserver(function (entries) {
      paybar.classList.toggle('is-hidden', entries[0].isIntersecting);
    }, { threshold: 0.15 }).observe(summary);
    $('[data-paybar-go]', paybar).addEventListener('click', function (e) {
      e.preventDefault();
      summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var cgv = $('[data-cgv]');
      if (cgv && !cgv.checked) setTimeout(function () { cgv.focus({ preventScroll: true }); cgv.closest('.checkbox').classList.add('pulse'); }, 450);
    });
  }

  // Option cadeau : affiche le message, compteur de caractères
  var giftToggle = $('[data-gift-toggle]');
  if (giftToggle) {
    var giftFields = $('[data-gift-fields]');
    var giftMsg = $('#gift-message');
    var giftCount = $('[data-gift-count]');
    var syncGift = function () { giftFields.hidden = !giftToggle.checked; if (giftToggle.checked) giftMsg.focus(); };
    giftToggle.addEventListener('change', syncGift);
    giftMsg.addEventListener('input', function () { giftCount.textContent = giftMsg.value.length + ' / 200'; });
  }

  // Pied de page : colonnes repliées sur téléphone
  if (window.matchMedia('(max-width: 760px)').matches) {
    $$('[data-footer-col]').forEach(function (d) { d.open = false; });
  }
  // Carrousel des collections : points de position
  var dots = $('[data-scroll-dots]');
  var colTrack = dots && dots.parentNode.querySelector('.collections');
  if (colTrack) {
    colTrack.addEventListener('scroll', function () {
      var cards = colTrack.children, best = 0, mid = colTrack.scrollLeft + colTrack.clientWidth / 2;
      for (var i = 0; i < cards.length; i++) if (Math.abs(cards[i].offsetLeft + cards[i].offsetWidth / 2 - mid) < Math.abs(cards[best].offsetLeft + cards[best].offsetWidth / 2 - mid)) best = i;
      $$('span', dots).forEach(function (s, i) { s.classList.toggle('active', i === best); });
    }, { passive: true });
  }

  // Recherche instantanée : suggestions au fil de la frappe, navigation au clavier
  var searchForm = $('[data-search-form]');
  if (searchForm && window.fetch) {
    var sInput = $('input[name="q"]', searchForm);
    var sBox = $('#search-results', searchForm);
    var sTimer = null, sSeq = 0, sActive = -1;
    var sClose = function () { sBox.hidden = true; sInput.setAttribute('aria-expanded', 'false'); sActive = -1; };
    var sItems = function () { return $$('[role="option"]', sBox); };
    var sMark = function (i) {
      var items = sItems();
      sActive = Math.max(-1, Math.min(items.length - 1, i));
      items.forEach(function (el, k) { el.classList.toggle('active', k === sActive); el.setAttribute('aria-selected', k === sActive ? 'true' : 'false'); });
    };
    var sRender = function (data) {
      sBox.innerHTML = '';
      if (!data.items.length) {
        var none = document.createElement('p');
        none.className = 'search-empty';
        none.textContent = 'Aucun article pour « ' + data.q + ' ». Essaie « sweat », « mug » ou « portrait ».';
        sBox.appendChild(none);
      }
      data.items.forEach(function (it) {
        var a = document.createElement('a');
        a.href = it.url; a.className = 'search-item'; a.setAttribute('role', 'option');
        var img = document.createElement('img'); img.src = it.image; img.alt = ''; img.width = 52; img.height = 52;
        var txt = document.createElement('span'); txt.className = 'search-item-text';
        var nm = document.createElement('strong'); nm.textContent = it.name;
        var meta = document.createElement('small'); meta.textContent = (it.category || '') + (it.custom ? ' · à personnaliser' : '');
        var pr = document.createElement('span'); pr.className = 'search-item-price'; pr.textContent = it.price;
        txt.appendChild(nm); txt.appendChild(meta); a.appendChild(img); a.appendChild(txt); a.appendChild(pr);
        sBox.appendChild(a);
      });
      if (data.total > data.items.length) {
        var all = document.createElement('a');
        all.href = data.all_url; all.className = 'search-all'; all.setAttribute('role', 'option');
        all.textContent = 'Voir les ' + data.total + ' résultats →';
        sBox.appendChild(all);
      }
      sBox.hidden = false; sInput.setAttribute('aria-expanded', 'true'); sActive = -1;
    };
    sInput.addEventListener('input', function () {
      clearTimeout(sTimer);
      var q = sInput.value.trim();
      if (q.length < 2) { sClose(); return; }
      sTimer = setTimeout(function () {
        var seq = ++sSeq;
        fetch(searchForm.dataset.suggestUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) { if (seq === sSeq) sRender(data); })
          .catch(function () {});
      }, 180);
    });
    sInput.addEventListener('keydown', function (e) {
      if (sBox.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); sMark(sActive + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sMark(sActive - 1); }
      else if (e.key === 'Enter' && sActive >= 0) { e.preventDefault(); sItems()[sActive].click(); }
      else if (e.key === 'Escape') sClose();
    });
    document.addEventListener('click', function (e) { if (!searchForm.contains(e.target)) sClose(); });
  }

  // Liste d'envies (♡) : navigateur pour toutes, compte pour les clientes connectées ----------
  var WISH_KEY = 'cc_wishlist';
  var body = document.body;
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  var wishRead = function () { try { var v = JSON.parse(store.get(WISH_KEY) || '[]'); return Array.isArray(v) ? v.map(Number).filter(Boolean) : []; } catch (e) { return []; } };
  var wishWrite = function (ids) { store.set(WISH_KEY, JSON.stringify(ids.slice(0, 60))); };
  var wishPost = function (data) {
    if (!body.dataset.wishlistServer || !window.fetch) return Promise.resolve(null);
    var fd = new FormData();
    fd.append('_token', csrf);
    (data.ids || []).forEach(function (id) { fd.append('ids[]', id); });
    (data.removed || []).forEach(function (id) { fd.append('removed[]', id); });
    return fetch(body.dataset.wishlistSync, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).catch(function () { return null; });
  };
  var wishRender = function () {
    var ids = wishRead();
    $$('[data-wish]').forEach(function (b) {
      var on = ids.indexOf(parseInt(b.dataset.wish, 10)) !== -1;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    $$('[data-wish-count]').forEach(function (c) { c.textContent = ids.length; c.hidden = !ids.length; });
    $$('[data-wish-link]').forEach(function (a) { a.href = body.dataset.wishlistUrl + (ids.length && !body.dataset.wishlistServer ? '?ids=' + ids.join(',') : ''); });
  };
  if (body.dataset.wishlistServer) {
    // Connectée : la liste du navigateur rejoint celle du compte
    var server = []; try { server = JSON.parse(body.dataset.wishlistServer); } catch (e) {}
    var local = wishRead();
    var missing = local.filter(function (id) { return server.indexOf(id) === -1; });
    wishWrite(server.concat(missing));
    if (missing.length) wishPost({ ids: missing }).then(function (r) { if (r && r.ids) { wishWrite(r.ids); wishRender(); } });
  }
  wishRender();
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-wish]');
    if (!b) return;
    e.preventDefault();
    var id = parseInt(b.dataset.wish, 10), ids = wishRead(), i = ids.indexOf(id);
    if (i === -1) { ids.unshift(id); wishPost({ ids: [id] }); } else { ids.splice(i, 1); wishPost({ removed: [id] }); }
    wishWrite(ids);
    wishRender();
    b.classList.add('pop'); setTimeout(function () { b.classList.remove('pop'); }, 400);
    var page = b.closest('[data-wishlist-page]');
    if (page && i !== -1) { var card = b.closest('.product-card'); if (card) { card.style.opacity = '.35'; } }
  });
  // Page « Mes favoris » d'une visiteuse : la liste vient du navigateur
  var wishPage = $('[data-wishlist-page]');
  if (wishPage && wishPage.dataset.loaded === '0') {
    var mine = wishRead();
    if (mine.length) location.replace(body.dataset.wishlistUrl + '?ids=' + mine.join(','));
    else { var acc = $('.script-accent', wishPage); if (acc) acc.textContent = 'aucun favori pour l\'instant'; }
  }
  var shareBtn = $('[data-wish-share]');
  if (shareBtn) {
    shareBtn.addEventListener('click', function () {
      var name = ($('[data-wish-name]') || {}).value || '';
      var link = body.dataset.wishlistUrl + '?partage=1&ids=' + wishRead().join(',') + (name.trim() ? '&de=' + encodeURIComponent(name.trim()) : '');
      var out = $('[data-wish-share-result]');
      var done = function (msg) { out.hidden = false; out.textContent = msg; };
      if (navigator.share) {
        navigator.share({ title: 'Ma liste d\'envies', text: 'Des idées cadeaux qui me feraient plaisir ♡', url: link }).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(function () { done('Lien copié ! Colle-le dans un message ou un e-mail.'); }, function () { done(link); });
      } else {
        done(link);
      }
    });
  }

  // Apparitions douces au défilement : seulement les éléments encore hors de l'écran
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var revealIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); revealIo.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    // (pas les cartes des carrousels horizontaux : hors écran sur le côté, elles resteraient invisibles)
    var revealSel = '.section-head, .product-grid > *, .collections, .feature-visual, .feature-text, .testimonial, .club, .gift-banner, .reassurance-grid > *, .lp-example, .lp-steps > li, .journal-card';
    $$(revealSel).forEach(function (el) {
      if (el.getBoundingClientRect().top < window.innerHeight) return;
      var siblings = el.parentNode ? Array.prototype.indexOf.call(el.parentNode.children, el) : 0;
      el.style.setProperty('--reveal-delay', (Math.min(siblings, 3) * 0.08) + 's');
      el.classList.add('reveal');
      revealIo.observe(el);
    });
  }

  // Transition de page : la photo du produit cliqué « glisse » vers la fiche produit
  if (document.startViewTransition !== undefined || 'onpagereveal' in window) {
    document.addEventListener('click', function (e) {
      var link = e.target.closest('.product-card-link');
      if (!link) return;
      var img = link.querySelector('.product-card-media img');
      if (img) img.style.viewTransitionName = 'product-photo';
    });
  }

  // Assistant cadeau : passe à la question suivante après chaque réponse
  var finder = $('[data-gift-finder]');
  if (finder) {
    $$('input[type="radio"]', finder).forEach(function (r) {
      r.addEventListener('change', function () {
        var step = r.closest('.gift-step'), next = step.nextElementSibling;
        if (next && next.classList.contains('gift-step')) next.scrollIntoView({ behavior: 'smooth', block: 'center' });
        else $('button[type="submit"]', finder).focus();
      });
    });
    if (location.search && $('#resultats')) $('#resultats').scrollIntoView();
  }

  // Mini-panier ------------------------------------------------------------
  var drawer = $('[data-drawer]');
  var backdrop = $('[data-drawer-backdrop]');
  var drawerContent = $('[data-drawer-content]');
  var setCount = function (n) {
    $$('[data-cart-count]').forEach(function (cartCount) {
      cartCount.textContent = n;
      cartCount.hidden = !n;
      cartCount.classList.remove('bump'); void cartCount.offsetWidth; cartCount.classList.add('bump');
    });
  };
  var openDrawer = function (html) {
    if (!drawer) return;
    if (html !== undefined) drawerContent.innerHTML = html;
    backdrop.hidden = false;
    requestAnimationFrame(function () { backdrop.classList.add('open'); drawer.classList.add('open'); });
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    drawer.focus();
  };
  var closeDrawer = function () {
    if (!drawer) return;
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    setTimeout(function () { backdrop.hidden = true; }, 300);
  };
  if (drawer) {
    $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer.classList.contains('open')) closeDrawer(); });
    if (location.pathname.indexOf('/panier') === -1) $$('[data-cart-open]').forEach(function (cartLink) {
      cartLink.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        openDrawer('<div class="drawer-body"><p class="muted">Chargement…</p></div>');
        fetch(document.body.dataset.cartUrl, { credentials: 'same-origin' })
          .then(function (r) { return r.text(); })
          .then(function (html) { drawerContent.innerHTML = html; })
          .catch(function () { location.href = cartLink.href; });
      });
    });
  }

  // Fiche produit : variantes, personnalisation, ajout au panier ----------
  var form = $('[data-buy-form]');
  if (form) {
    var variants = JSON.parse(($('[data-variants]', form) || {}).textContent || '[]');
    var variantInput = $('[data-variant-input]', form);
    var priceEls = $$('[data-price]');
    var addBtn = $('[data-add-button]', form);
    var unavailable = $('[data-unavailable]', form);
    var errorBox = $('[data-form-error]', form);
    var stage = $('[data-preview-stage]');
    var aiLocked = !!$('[data-ai-box], [data-manga-box]', form);
    var baseSwapped = false;
    var useBaseImage = function () {
      if (!baseSwapped && stage && stage.dataset.previewBase && mainImage) {
        setMain(stage.dataset.previewBase);
        baseSwapped = true;
      }
    };

    var sizeList = variants.map(function (v) { return v.size; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
    var selected = function (name) {
      var el = $('input[data-option="' + name + '"]:checked', form);
      return el ? el.value : null;
    };
    var sync = function () {
      var color = selected('color');
      var size = selected('size');
      var match = variants.filter(function (v) {
        return (color === null || v.color === color) && (size === null || v.size === size);
      })[0];
      $$('input[data-option="size"]', form).forEach(function (input) {
        input.disabled = color !== null && !variants.some(function (v) { return v.color === color && v.size === input.value; });
      });
      var cIn = $('input[data-option="color"]:checked', form);
      var c = $('[data-selected-color]'); if (c) c.textContent = (cIn && cIn.dataset.label) || color || '';
      var s = $('[data-selected-size]'); if (s) s.textContent = size || '';
      if (match) {
        variantInput.value = match.id;
        priceEls.forEach(function (el) { el.textContent = match.priceLabel; });
        if (match.image && mainImage && !baseSwapped) setMain(match.image);
        if (aiStage && match.colorHex) aiStage.style.setProperty('--frame', match.colorHex);
        if (aiStage) aiStage.classList.toggle('is-large', sizeList.indexOf(match.size) > 0); // grand format : cadre plus grand sur le mur
        addBtn.disabled = aiLocked;
        unavailable.hidden = true;
      } else {
        addBtn.disabled = true;
        unavailable.hidden = false;
      }
    };
    $$('input[data-option]', form).forEach(function (input) { input.addEventListener('change', sync); });
    sync();

    // Aperçu du texte
    var persoInput = $('[data-personalization]', form);
    var previewText = $('[data-preview-text]');
    var charCount = $('[data-char-count]');
    if (persoInput) {
      var updateText = function () {
        var clean = persoInput.value.replace(/[^\p{L}\p{N} '’&.\-]/gu, '');
        if (clean !== persoInput.value) persoInput.value = clean;
        if (clean) useBaseImage();
        if (previewText) previewText.textContent = clean;
        if (charCount) charCount.textContent = clean.length;
      };
      persoInput.addEventListener('input', updateText);
      updateText();
    }

    // Aperçu de la photo
    var photoInput = $('[data-photo-input]', form);
    if (photoInput) {
      var drop = $('[data-photo-drop]', form);
      var thumb = $('[data-photo-thumb]', form);
      var label = $('[data-photo-label]', form);
      var previewPhoto = $('[data-preview-photo]');
      ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.add('dragover'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('dragover'); }); });
      photoInput.addEventListener('change', function () {
        var file = photoInput.files && photoInput.files[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) {
          photoInput.value = '';
          errorBox.textContent = 'Ta photo est trop lourde (20 Mo maximum).';
          errorBox.hidden = false;
          return;
        }
        errorBox.hidden = true;
        var urlObj = URL.createObjectURL(file);
        thumb.style.backgroundImage = 'url("' + urlObj + '")';
        thumb.hidden = false;
        drop.classList.add('has-photo');
        label.textContent = 'Photo ajoutée ✓ (clique pour changer)';
        if (previewPhoto) {
          useBaseImage();
          previewPhoto.style.backgroundImage = 'url("' + urlObj + '")';
          previewPhoto.hidden = false;
        }
      });
    }

    // Portrait IA « Encre & Poudre » : photo → dessin → aperçu encadré avec nom et année
    var aiBox = $('[data-ai-box]', form);
    if (aiBox && aiStage) {
      var geo = JSON.parse(aiStage.dataset.geo);
      var svg = $('[data-ai-svg]', aiStage);
      var art = $('[data-ai-art]', svg);
      var nameEl = $('[data-ai-name]', svg);
      var yearEl = $('[data-ai-year]', svg);
      var rules = $$('[data-ai-rule]', svg);
      var maskRect = $('[data-ai-mask]', svg);
      var note = $('[data-ai-stage-note]', aiStage);
      var aiPhoto = $('[data-ai-photo]', aiBox);
      var aiConsent = $('[data-ai-consent]', aiBox);
      var genBtn = $('[data-ai-generate]', aiBox);
      var startBox = $('[data-ai-start]', aiBox);
      var progress = $('[data-ai-progress]', aiBox);
      var bar = $('[data-ai-bar]', aiBox);
      var progressText = $('[data-ai-progress-text]', aiBox);
      var resultBox = $('[data-ai-result]', aiBox);
      var aiThumb = $('[data-ai-thumb]', aiBox);
      var aiError = $('[data-ai-error]', aiBox);
      var remainingEl = $('[data-ai-remaining]', aiBox);
      var tokenInput = $('[data-ai-token]', form);
      var yearInput = $('[data-ai-year-input]', form);
      var addLabel = addBtn.textContent;
      var showBtn = $('[data-ai-show]');
      var hasOwnPortrait = false;
      var styleInputs = $$('[data-ai-style]', aiBox);
      var genLabel = $('[data-ai-generate-label]', aiBox);
      var versions = [];          // créations de la visiteuse : { token, preview, style, name }
      var photoToken = null;      // portrait déjà créé avec la photo actuelle : les autres styles la réutilisent
      var currentStyle = function () {
        var checked = styleInputs.filter(function (i) { return i.checked; })[0] || styleInputs[0];
        return checked;
      };

      var showStage = function () {
        aiStage.hidden = false;
        $$('[data-thumb], [data-ai-show]').forEach(function (t) { t.classList.remove('active'); });
        if (showBtn) showBtn.classList.add('active');
      };
      if (showBtn) showBtn.addEventListener('click', showStage);

      var setLocked = function (locked) {
        aiLocked = locked;
        addBtn.disabled = locked;
        addBtn.textContent = locked ? 'Crée son portrait \u2191' : addLabel;
      };
      setLocked(true);

      // Mise en page identique au fichier d'impression (voir AiPortrait::composePrint)
      var layout = function () {
        var typedName = persoInput ? persoInput.value.trim() : '';
        var typedYear = yearInput ? yearInput.value : '';
        var example = !hasOwnPortrait && !typedName && !typedYear; // exemple tant que rien n'est saisi
        var name = example ? 'Tornado' : typedName;
        var year = example && yearInput ? '2014' : typedYear;
        nameEl.textContent = name;
        nameEl.setAttribute('font-size', geo.nameSize);
        var size = geo.nameSize;
        while (name && size > 30 && nameEl.getComputedTextLength() > geo.nameMaxW) {
          size *= 0.95;
          nameEl.setAttribute('font-size', size);
        }
        nameEl.setAttribute('y', year ? geo.nameY : geo.nameYAlone);
        yearEl.textContent = year;
        yearEl.setAttribute('y', name ? geo.yearY : geo.nameYAlone);
        yearEl.setAttribute('dx', geo.yearSpacing / 2); // l'espacement final décale le centrage
        var width = year ? yearEl.getComputedTextLength() - geo.yearSpacing : 0;
        var mid = (name ? geo.yearY : geo.nameYAlone) - geo.yearSize * 0.36;
        rules.forEach(function (r) {
          var side = parseInt(r.dataset.aiRule, 10);
          var from = 1800 + side * (width / 2 + geo.ruleGap);
          r.setAttribute('x', side < 0 ? from - geo.ruleLen : from);
          r.setAttribute('y', mid - geo.ruleThick / 2);
          r.style.display = year ? '' : 'none';
        });
      };
      // Le fondu des bords suit les proportions réelles du dessin
      var fitMask = function (w, h) {
        var scale = Math.min(geo.artW / w, geo.artH / h);
        var dw = w * scale, dh = h * scale, inset = Math.min(dw, dh) * 0.035;
        maskRect.setAttribute('x', geo.artX + (geo.artW - dw) / 2 + inset);
        maskRect.setAttribute('y', geo.artY + (geo.artH - dh) / 2 + inset);
        maskRect.setAttribute('width', dw - 2 * inset);
        maskRect.setAttribute('height', dh - 2 * inset);
      };
      var setArt = function (src) {
        var probe = new Image();
        probe.onload = function () { fitMask(probe.naturalWidth, probe.naturalHeight); art.setAttribute('href', src); };
        probe.src = src;
      };
      setArt(art.getAttribute('href'));
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(layout);
      layout();
      if (persoInput) persoInput.addEventListener('input', function () { showStage(); layout(); });
      if (yearInput) {
        yearInput.addEventListener('input', function () {
          var clean = yearInput.value.replace(/\D/g, '').slice(0, 4);
          if (clean !== yearInput.value) yearInput.value = clean;
          showStage();
          layout();
        });
      }

      var fail = function (message) {
        aiError.textContent = message || 'Une erreur est survenue, merci de réessayer.';
        aiError.hidden = false;
        progress.hidden = true;
        startBox.hidden = false;
        if (hasOwnPortrait) resultBox.hidden = false;
        updateGenerate();
      };
      var updateGenerate = function () {
        genBtn.disabled = !(((aiPhoto.files && aiPhoto.files[0]) || photoToken) && aiConsent.checked);
        var style = currentStyle();
        if (genLabel && style) {
          genLabel.textContent = versions.length ? 'Créer la version « ' + style.dataset.name + ' »' : 'Créer mon portrait';
        }
      };
      // Liste des créations : on passe de l'une à l'autre sans rien recréer
      var versionsBox = $('[data-ai-versions]', aiBox);
      var versionsList = $('[data-ai-versions-list]', aiBox);
      var selectVersion = function (v) {
        tokenInput.value = v.token;
        aiThumb.src = v.preview;
        setArt(v.preview);
        $('[data-ai-result-style]', aiBox).textContent = 'Style « ' + v.name + ' » · le nom s’ajoute ci-dessous';
        $$('button', versionsList).forEach(function (b) { b.classList.toggle('active', b.dataset.token === v.token); });
        showStage();
      };
      var renderVersions = function () {
        versionsList.innerHTML = '';
        versions.forEach(function (v) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'ai-version';
          b.dataset.token = v.token;
          b.innerHTML = '<img alt="" width="54" height="72"><span></span>';
          b.querySelector('img').src = v.preview;
          b.querySelector('span').textContent = v.name;
          b.addEventListener('click', function () { selectVersion(v); });
          versionsList.appendChild(b);
        });
        versionsBox.hidden = versions.length < 2;
      };
      // Les images d'exemple de la galerie présélectionnent leur style
      $$('[data-style-key]').forEach(function (t) {
        t.addEventListener('click', function () {
          var input = styleInputs.filter(function (i) { return i.value === t.dataset.styleKey; })[0];
          if (input) { input.checked = true; updateGenerate(); }
        });
      });
      styleInputs.forEach(function (input) {
        input.addEventListener('change', function () {
          var mine = versions.filter(function (v) { return v.style === input.value; }).pop();
          if (mine) selectVersion(mine);
          else if (!hasOwnPortrait && input.dataset.example) { setArt(input.dataset.example); note.textContent = 'Exemple du style « ' + input.dataset.name + ' » · ton portrait apparaîtra ici'; showStage(); }
          updateGenerate();
          track('ai_portrait_style', { style: input.value });
        });
      });
      var updateRemaining = function (n) {
        if (typeof n !== 'number' || !remainingEl) return;
        remainingEl.textContent = n > 0
          ? 'Environ 30 secondes · ' + n + ' création' + (n > 1 ? 's' : '') + ' offerte' + (n > 1 ? 's' : '') + ' aujourd’hui'
          : 'C’était ta dernière création offerte du jour ♡';
      };

      aiPhoto.addEventListener('change', function () {
        var file = aiPhoto.files && aiPhoto.files[0];
        aiError.hidden = true;
        if (file && file.size > 20 * 1024 * 1024) {
          aiPhoto.value = '';
          fail('Ta photo est trop lourde (20 Mo maximum).');
          return;
        }
        if (file) {
          photoToken = null; // nouvelle photo : elle sera envoyée
          var thumbEl = $('[data-photo-thumb]', aiBox);
          thumbEl.style.backgroundImage = 'url("' + URL.createObjectURL(file) + '")';
          thumbEl.hidden = false;
          $('[data-photo-drop]', aiBox).classList.add('has-photo');
          $('[data-photo-label]', aiBox).textContent = 'Photo ajoutée ✓ (clique pour changer)';
        }
        updateGenerate();
      });
      aiConsent.addEventListener('change', updateGenerate);
      var aiDrop = $('[data-photo-drop]', aiBox);
      ['dragenter', 'dragover'].forEach(function (ev) { aiDrop.addEventListener(ev, function () { aiDrop.classList.add('dragover'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { aiDrop.addEventListener(ev, function () { aiDrop.classList.remove('dragover'); }); });

      var stepTimer = null;
      var startProgress = function () {
        var i = 0;
        var styleSteps = [];
        try { styleSteps = JSON.parse(currentStyle().dataset.steps || '[]'); } catch (e) {}
        var steps = ['Analyse de la photo…', 'Détourage de la tête et de l’encolure…', 'Esquisse de la tête et des oreilles…'].concat(styleSteps, ['Derniers détails dans le regard…', 'Encore quelques secondes, on peaufine…']);
        startBox.hidden = true;
        resultBox.hidden = true;
        aiError.hidden = true;
        progress.hidden = false;
        progressText.textContent = steps[0];
        bar.style.transition = 'none';
        bar.style.width = '3%';
        void bar.offsetWidth;
        bar.style.transition = 'width 60s cubic-bezier(.2,.6,.3,1)';
        bar.style.width = '92%';
        clearInterval(stepTimer);
        stepTimer = setInterval(function () { i = Math.min(steps.length - 1, i + 1); progressText.textContent = steps[i]; }, 7000);
      };
      var ready = function (state) {
        clearInterval(stepTimer);
        bar.style.transition = 'width .3s';
        bar.style.width = '100%';
        hasOwnPortrait = true;
        photoToken = state.token;
        var version = { token: state.token, preview: state.preview, style: state.style, name: state.style_name };
        versions.push(version);
        renderVersions();
        selectVersion(version);
        updateGenerate();
        note.textContent = 'Aperçu réduit et filigrané · impression en haute définition';
        setTimeout(function () {
          progress.hidden = true;
          resultBox.hidden = false;
          startBox.hidden = true;
        }, 400);
        showStage();
        layout();
        setLocked(false);
        if (persoInput && !persoInput.value) persoInput.focus({ preventScroll: true });
        track('ai_portrait_ready', { style: state.style });
      };
      var poll = function (token, started) {
        if (Date.now() - started > 4 * 60 * 1000) { clearInterval(stepTimer); fail('Le dessin prend plus de temps que prévu. Réessaie dans un instant.'); return; }
        setTimeout(function () {
          fetch(aiBox.dataset.aiUrl + '/' + token, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (state) { handle(state, started); })
            .catch(function () { poll(token, started); });
        }, 3000);
      };
      // Pendant que la cliente personnalise, le serveur récupère la version agrandie pour l'impression
      var followUpscale = function (token, tries) {
        if (tries <= 0) return;
        setTimeout(function () {
          fetch(aiBox.dataset.aiUrl + '/' + token, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (state) { if (state.upscaling) followUpscale(token, tries - 1); })
            .catch(function () {});
        }, 6000);
      };
      var handle = function (state, started) {
        if (state.status === 'ready') { ready(state); if (state.upscaling) followUpscale(state.token, 15); }
        else if (state.status === 'failed') { clearInterval(stepTimer); fail(state.message + (state.admin ? '\n\n🔧 Diagnostic (visible car tu es connectée à l\u2019administration) :\n' + state.admin : '')); }
        else poll(state.token, started);
      };

      var shrink = shrinkPhoto;

      genBtn.addEventListener('click', function () {
        var file = aiPhoto.files && aiPhoto.files[0];
        if ((!file && !photoToken) || !aiConsent.checked) return;
        genBtn.disabled = true;
        startProgress();
        showStage();
        track('ai_portrait_start', { style: currentStyle().value });
        (photoToken ? Promise.resolve(null) : shrink(file)).then(function (photo) {
          var data = new FormData();
          data.append('_token', $('input[name="_token"]', form).value);
          data.append('product_id', aiBox.dataset.productId);
          data.append('consent', '1');
          data.append('style', currentStyle().value);
          $$('[data-ai-mark]', aiBox).forEach(function (m) { if (m.checked) data.append('marks[]', m.value); });
          if (photo) data.append('photo', photo);
          else data.append('from', photoToken);
          return fetch(aiBox.dataset.aiUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        })
          .then(function (r) { return r.json().catch(function () { throw new Error('L\u2019envoi de la photo a échoué, merci de réessayer.'); }); })
          .then(function (state) {
            if (!state.ok) throw new Error(state.message);
            updateRemaining(state.remaining);
            handle(state, Date.now());
          })
          .catch(function (err) { clearInterval(stepTimer); fail(err.message); });
      });
      $('[data-ai-retry]', aiBox).addEventListener('click', function () {
        resultBox.hidden = true;
        startBox.hidden = false;
        // Propose d'emblée un style pas encore essayé
        var tried = versions.map(function (v) { return v.style; });
        var next = styleInputs.filter(function (i) { return tried.indexOf(i.value) < 0; })[0];
        if (next) next.checked = true;
        updateGenerate();
        var cards = $('.ai-styles', aiBox);
        (cards || aiPhoto).scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    }

    // Illustration manga (t-shirt) : photo de la cavalière + photo du cheval → illustration posée sur le t-shirt
    var mangaBox = $('[data-manga-box]', form);
    if (mangaBox) {
      var mPhotos = { rider: $('[data-manga-photo="rider"]', mangaBox), horse: $('[data-manga-photo="horse"]', mangaBox) };
      var mConsent = $('[data-manga-consent]', mangaBox);
      var mGen = $('[data-manga-generate]', mangaBox);
      var mGenLabel = $('[data-manga-generate-label]', mangaBox);
      var mStart = $('[data-manga-start]', mangaBox);
      var mProgress = $('[data-manga-progress]', mangaBox);
      var mBar = $('[data-manga-bar]', mangaBox);
      var mText = $('[data-manga-progress-text]', mangaBox);
      var mResult = $('[data-manga-result]', mangaBox);
      var mError = $('[data-manga-error]', mangaBox);
      var mToken = $('[data-manga-token]', mangaBox);
      var mVersionsBox = $('[data-manga-versions]', mangaBox);
      var mVersionsList = $('[data-manga-versions-list]', mangaBox);
      var mOverlay = $('[data-manga-print]');
      var mArt = $('[data-manga-art]');
      var mNamesEl = $('[data-manga-names]');
      var mNameInputs = $$('[data-manga-name]', mangaBox);
      var mSample = $('[data-manga-names-sample]', mangaBox);
      // Prénom de la cavalière & nom du cheval, comme sur le fichier d'impression
      var mNames = function () {
        var vals = mNameInputs.map(function (i) {
          var clean = i.value.replace(/[^\p{L}\p{N} '’&.\-]/gu, '');
          if (clean !== i.value) i.value = clean;
          return clean.trim();
        }).filter(Boolean);
        var text = vals.join(' & ');
        if (mNamesEl) mNamesEl.textContent = text;
        if (mSample) mSample.textContent = text || 'Léa & Tornado';
      };
      mNameInputs.forEach(function (i) { i.addEventListener('input', mNames); });
      var mVersions = [];
      var mFrom = null; // création déjà faite avec ces photos : l'autre vue les réutilise
      var mTimer = null;
      var addLabelM = addBtn.textContent;
      var mView = function () { return ($('[data-manga-view]:checked', mangaBox) || {}).value || 'dos'; };
      var mHasPhotos = function () { return mPhotos.rider.files.length && mPhotos.horse.files.length; };
      var mUpdate = function () {
        mGen.disabled = !((mFrom || mHasPhotos()) && mConsent.checked);
        mGenLabel.textContent = mVersions.length ? 'Créer la vue « ' + ($('[data-manga-view]:checked + span', mangaBox) || {}).textContent + ' »' : 'Créer notre illustration';
      };
      var mLock = function (locked) {
        aiLocked = locked;
        addBtn.disabled = locked;
        addBtn.textContent = locked ? 'Crée votre illustration ↑' : addLabelM;
      };
      mLock(true);
      Object.keys(mPhotos).forEach(function (k) {
        var input = mPhotos[k];
        input.addEventListener('change', function () {
          var file = input.files && input.files[0];
          mError.hidden = true;
          if (file && file.size > 20 * 1024 * 1024) { input.value = ''; mError.textContent = 'Ta photo est trop lourde (20 Mo maximum).'; mError.hidden = false; return; }
          if (file) {
            var drop = input.closest('[data-manga-drop]');
            var th = $('.photo-thumb', drop);
            th.style.backgroundImage = 'url("' + URL.createObjectURL(file) + '")';
            th.hidden = false;
            drop.classList.add('has-photo');
            mFrom = null; // nouvelles photos : elles seront envoyées
          }
          mUpdate();
        });
      });
      mConsent.addEventListener('change', mUpdate);
      $$('[data-manga-view]', mangaBox).forEach(function (r) {
        r.addEventListener('change', function () {
          var mine = mVersions.filter(function (v) { return v.view === r.value; }).pop();
          if (mine) mSelect(mine);
          mUpdate();
        });
      });
      // Aperçu posé sur la photo du t-shirt vierge ; sans photo de t-shirt, l'illustration s'affiche en grand
      var mShow = function (preview) {
        $$('[data-thumb]').forEach(function (t) { t.classList.remove('active'); });
        if (!mOverlay) { setMain(preview); return; }
        if (mangaBox.dataset.baseImage) setMain(mangaBox.dataset.baseImage);
        stage.classList.toggle('manga-only', !mangaBox.dataset.baseImage); // sans photo de t-shirt vierge : la feuille seule
        mArt.style.backgroundImage = 'url("' + preview + '")';
        mOverlay.hidden = false;
        mNames();
      };
      var mSelect = function (v) {
        mToken.value = v.token;
        $('[data-manga-thumb]', mangaBox).src = v.preview;
        $('[data-manga-result-view]', mangaBox).textContent = 'Vue ' + v.name.toLowerCase() + ' · aperçu sur le t-shirt';
        $$('button', mVersionsList).forEach(function (b) { b.classList.toggle('active', b.dataset.token === v.token); });
        mShow(v.preview);
      };
      var mRender = function () {
        mVersionsList.innerHTML = '';
        mVersions.forEach(function (v) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'ai-version';
          b.dataset.token = v.token;
          b.innerHTML = '<img alt="" width="54" height="72"><span></span>';
          b.querySelector('img').src = v.preview;
          b.querySelector('span').textContent = v.name;
          b.addEventListener('click', function () { mSelect(v); });
          mVersionsList.appendChild(b);
        });
        mVersionsBox.hidden = mVersions.length < 2;
      };
      var mFail = function (message) {
        clearInterval(mTimer);
        mError.textContent = message || 'Une erreur est survenue, merci de réessayer.';
        mError.hidden = false;
        mProgress.hidden = true;
        mStart.hidden = false;
        if (mVersions.length) mResult.hidden = false;
        mUpdate();
      };
      var mReady = function (state) {
        clearInterval(mTimer);
        mBar.style.transition = 'width .3s';
        mBar.style.width = '100%';
        mFrom = state.token;
        var v = { token: state.token, preview: state.preview, view: state.view, name: state.style_name };
        mVersions.push(v);
        mRender();
        mSelect(v);
        setTimeout(function () { mProgress.hidden = true; mResult.hidden = false; }, 400);
        mLock(false);
        mUpdate();
        track('ai_manga_ready', { view: state.view });
      };
      var mHandle = function (state, started) {
        if (state.status === 'ready') mReady(state);
        else if (state.status === 'failed') mFail(state.message + (state.admin ? '\n\n🔧 Diagnostic (visible car tu es connectée à l’administration) :\n' + state.admin : ''));
        else if (Date.now() - started > 4 * 60 * 1000) mFail('Le dessin prend plus de temps que prévu. Réessaie dans un instant.');
        else setTimeout(function () {
          fetch(mangaBox.dataset.statusUrl + '/' + state.token, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (next) { mHandle(next, started); })
            .catch(function () { mHandle(state, started); });
        }, 3000);
      };
      mGen.addEventListener('click', function () {
        if (mGen.disabled) return;
        mGen.disabled = true;
        mStart.hidden = true;
        mResult.hidden = true;
        mError.hidden = true;
        mProgress.hidden = false;
        var steps = ['Analyse des photos…', 'Mise en place de la scène champêtre…', 'Dessin de ton cheval…', 'Dessin de ta silhouette…', 'Encrage manga et couleurs…', 'Lumière dorée de fin d’après-midi…', 'Encore quelques secondes, on peaufine…'];
        var i = 0;
        mText.textContent = steps[0];
        mBar.style.transition = 'none';
        mBar.style.width = '3%';
        void mBar.offsetWidth;
        mBar.style.transition = 'width 50s cubic-bezier(.2,.6,.3,1)';
        mBar.style.width = '92%';
        mTimer = setInterval(function () { i = Math.min(steps.length - 1, i + 1); mText.textContent = steps[i]; }, 7000);
        track('ai_manga_start', { view: mView() });
        var send = mFrom ? Promise.resolve([null, null]) : Promise.all([shrinkPhoto(mPhotos.rider.files[0]), shrinkPhoto(mPhotos.horse.files[0])]);
        send.then(function (photos) {
          var data = new FormData();
          data.append('_token', $('input[name="_token"]', form).value);
          data.append('product_id', mangaBox.dataset.productId);
          data.append('consent', '1');
          data.append('view', mView());
          if (photos[0]) { data.append('rider_photo', photos[0]); data.append('horse_photo', photos[1]); }
          else data.append('from', mFrom);
          return fetch(mangaBox.dataset.url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        })
          .then(function (r) { return r.json().catch(function () { throw new Error('L’envoi des photos a échoué, merci de réessayer.'); }); })
          .then(function (state) {
            if (!state.ok) throw new Error(state.message);
            var rem = $('[data-manga-remaining]', mangaBox);
            if (typeof state.remaining === 'number' && rem) rem.textContent = state.remaining > 0 ? 'Environ 30 secondes · ' + state.remaining + ' création' + (state.remaining > 1 ? 's' : '') + ' offerte' + (state.remaining > 1 ? 's' : '') + ' aujourd’hui' : 'C’était ta dernière création offerte du jour ♡';
            mHandle(state, Date.now());
          })
          .catch(function (err) { mFail(err.message); });
      });
      $('[data-manga-retry]', mangaBox).addEventListener('click', function () {
        var other = $$('[data-manga-view]', mangaBox).filter(function (r) { return !r.checked; })[0];
        if (other) other.checked = true;
        mResult.hidden = true;
        mStart.hidden = false;
        mUpdate();
        mStart.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    }

    // Ajout au panier sans quitter la page
    form.addEventListener('submit', function (e) {
      if (e.submitter && e.submitter.hasAttribute('data-express')) return; // achat express : envoi classique vers le paiement
      if (!window.fetch || !window.FormData || !drawer) return;
      e.preventDefault();
      var data = new FormData(form);
      data.append('ajax', '1');
      addBtn.disabled = true;
      var label = addBtn.textContent;
      addBtn.textContent = 'Ajout en cours…';
      fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.json.ok) throw new Error(res.json.message || 'Une erreur est survenue, merci de réessayer.');
          errorBox.hidden = true;
          setCount(res.json.count);
          openDrawer(res.json.html);
          track('add_to_cart');
        })
        .catch(function (err) {
          errorBox.textContent = err.message || 'Une erreur est survenue, merci de réessayer.';
          errorBox.hidden = false;
        })
        .finally(function () { addBtn.disabled = false; addBtn.textContent = label; });
    });

    // Barre d'achat collante (mobile)
    var sticky = $('[data-sticky-buy]');
    if (sticky && 'IntersectionObserver' in window) {
      document.body.classList.add('has-sticky-buy');
      new IntersectionObserver(function (entries) {
        var visible = entries[0].isIntersecting || entries[0].boundingClientRect.top > 0;
        sticky.classList.toggle('visible', !visible);
        sticky.setAttribute('aria-hidden', visible ? 'true' : 'false');
      }).observe(addBtn);
      $('[data-sticky-add]', sticky).addEventListener('click', function () {
        if (persoInput || photoInput || aiLocked) {
          var target = (aiLocked && $('[data-ai-photo], [data-manga-photo]', form)) || photoInput || persoInput;
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(function () { target.focus(); }, 500);
        } else if (form.requestSubmit) {
          form.requestSubmit();
        } else {
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    }
  }

  // « Complète le look » : ajout en un clic, mini-panier ouvert
  $$('[data-quick-add]').forEach(function (qf) {
    qf.addEventListener('submit', function (e) {
      if (!window.fetch || !drawer) return;
      e.preventDefault();
      var btn = $('button', qf), data = new FormData(qf);
      data.append('ajax', '1');
      btn.disabled = true;
      fetch(qf.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.ok) { setCount(j.count); openDrawer(j.html); btn.textContent = '✓ Ajouté'; track('add_to_cart'); } else { btn.disabled = false; } })
        .catch(function () { qf.submit(); });
    });
  });

  // Newsletter (pied de page, bloc club, popup) ------------------------------
  $$('[data-newsletter]').forEach(function (nl) {
    nl.addEventListener('submit', function (e) {
      if (!window.fetch) return;
      e.preventDefault();
      var msg = $('[data-newsletter-message]', nl);
      var btn = $('button[type="submit"]', nl);
      var data = new FormData(nl);
      data.append('ajax', '1');
      btn.disabled = true;
      fetch(nl.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.message);
          store.set('cc_popup', 'subscribed');
          if (j.code) {
            nl.innerHTML = '<p>Bienvenue au club ! Voici ton code :</p><div class="welcome-code"><span></span><button type="button" class="btn btn-soft btn-sm">Copier</button></div><p class="small muted">Envoyé aussi par e-mail. À saisir au moment du paiement.</p>';
            $('.welcome-code span', nl).textContent = j.code;
            $('.welcome-code button', nl).addEventListener('click', function (ev) {
              if (navigator.clipboard) navigator.clipboard.writeText(j.code);
              ev.target.textContent = 'Copié ✓';
            });
          } else {
            nl.innerHTML = '<p class="flash flash-success"></p>';
            $('p', nl).textContent = j.message;
          }
          track('sign_up', { method: 'newsletter' });
        })
        .catch(function (err) {
          if (msg) msg.textContent = err.message || 'Une erreur est survenue.';
          btn.disabled = false;
        });
    });
  });

  // Consentement cookies + GA4 / Meta Pixel ----------------------------------
  var CONSENT_KEY = 'cc_consent';
  var banner = $('[data-cookie-banner]');
  var loadAnalytics = function () {
    var ga = document.body.dataset.ga4;
    if (ga && !window.gtag) {
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga);
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', ga, { anonymize_ip: true });
    }
    var px = document.body.dataset.pixel;
    if (px && !window.fbq) {
      /* eslint-disable */
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
      /* eslint-enable */
      window.fbq('init', px);
      window.fbq('track', 'PageView');
    }
  };
  var hasTrackers = document.body.dataset.ga4 || document.body.dataset.pixel;
  if (banner) {
    var consent = store.get(CONSENT_KEY);
    if (consent === 'accept') loadAnalytics();
    if (!consent && hasTrackers) banner.hidden = false;
    $$('[data-cookie-choice]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        store.set(CONSENT_KEY, btn.dataset.cookieChoice);
        banner.hidden = true;
        if (btn.dataset.cookieChoice === 'accept') loadAnalytics();
        else if (window.gtag) location.reload();
      });
    });
    $$('[data-cookie-settings]').forEach(function (btn) {
      btn.addEventListener('click', function () { banner.hidden = false; });
    });
  }

  // Popup de bienvenue : une fois par mois, après un délai ou une intention de sortie
  var welcome = $('[data-welcome]');
  if (welcome) {
    var last = parseInt(store.get('cc_popup_seen') || '0', 10);
    var eligible = store.get('cc_popup') !== 'subscribed' && Date.now() - last > 30 * 86400000;
    var shown = false;
    var show = function () {
      if (shown || !eligible || (banner && !banner.hidden) || document.querySelector('dialog[open]') || (drawer && drawer.classList.contains('open'))) return;
      shown = true;
      store.set('cc_popup_seen', String(Date.now()));
      openDialog(welcome);
    };
    // Pages vues pendant la visite : pas de popup dès la première page (on laisse découvrir)
    var views = 1;
    try { views = parseInt(sessionStorage.getItem('cc_pv') || '0', 10) + 1; sessionStorage.setItem('cc_pv', String(views)); } catch (e) {}
    if (eligible) {
      if (views >= 2) setTimeout(show, (parseInt(welcome.dataset.delay, 10) || 15) * 1000);
      // Intention de sortie (souris vers le haut de la fenêtre, ordinateur uniquement)
      document.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY < 10) show(); });
    }
  }
})();
