/* Crinière Club — interactions boutique (vanilla JS, sans dépendance) */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
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
  $$('[data-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      mainImage.src = thumb.dataset.thumb;
      $$('[data-thumb]').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });

  // Mini-panier ------------------------------------------------------------
  var drawer = $('[data-drawer]');
  var backdrop = $('[data-drawer-backdrop]');
  var drawerContent = $('[data-drawer-content]');
  var cartCount = $('[data-cart-count]');
  var setCount = function (n) {
    if (!cartCount) return;
    cartCount.textContent = n;
    cartCount.hidden = !n;
    cartCount.classList.remove('bump'); void cartCount.offsetWidth; cartCount.classList.add('bump');
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
    var cartLink = $('[data-cart-open]');
    if (cartLink && location.pathname.indexOf('/panier') === -1) {
      cartLink.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        openDrawer('<div class="drawer-body"><p class="muted">Chargement…</p></div>');
        fetch(document.body.dataset.cartUrl, { credentials: 'same-origin' })
          .then(function (r) { return r.text(); })
          .then(function (html) { drawerContent.innerHTML = html; })
          .catch(function () { location.href = cartLink.href; });
      });
    }
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
    var baseSwapped = false;
    var useBaseImage = function () {
      if (!baseSwapped && stage && stage.dataset.previewBase && mainImage) {
        mainImage.src = stage.dataset.previewBase;
        baseSwapped = true;
      }
    };

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
      var c = $('[data-selected-color]'); if (c) c.textContent = color || '';
      var s = $('[data-selected-size]'); if (s) s.textContent = size || '';
      if (match) {
        variantInput.value = match.id;
        priceEls.forEach(function (el) { el.textContent = match.priceLabel; });
        if (match.image && mainImage && !baseSwapped) mainImage.src = match.image;
        addBtn.disabled = false;
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
          errorBox.textContent = 'Votre photo est trop lourde (20 Mo maximum).';
          errorBox.hidden = false;
          return;
        }
        errorBox.hidden = true;
        var urlObj = URL.createObjectURL(file);
        thumb.style.backgroundImage = 'url("' + urlObj + '")';
        thumb.hidden = false;
        drop.classList.add('has-photo');
        label.textContent = 'Photo ajoutée ✓ (cliquez pour changer)';
        if (previewPhoto) {
          useBaseImage();
          previewPhoto.style.backgroundImage = 'url("' + urlObj + '")';
          previewPhoto.hidden = false;
        }
      });
    }

    // Ajout au panier sans quitter la page
    form.addEventListener('submit', function (e) {
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
        if (persoInput || photoInput) {
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(function () { (photoInput || persoInput).focus(); }, 500);
        } else if (form.requestSubmit) {
          form.requestSubmit();
        } else {
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    }
  }

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
            nl.innerHTML = '<p>Bienvenue au club ! Voici votre code :</p><div class="welcome-code"><span></span><button type="button" class="btn btn-soft btn-sm">Copier</button></div><p class="small muted">Envoyé aussi par e-mail. À saisir au moment du paiement.</p>';
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
    if (eligible) {
      setTimeout(show, (parseInt(welcome.dataset.delay, 10) || 15) * 1000);
      document.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY < 10) show(); });
    }
  }
})();
