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
  // Menu trop long pour la largeur (nombreuses catégories) : bascule en menu replié
  var siteHeader = $('.site-header');
  var fitNav = function () {
    if (!siteHeader || !nav) return;
    siteHeader.classList.remove('is-compact');
    if (window.innerWidth > 1060 && nav.scrollWidth > nav.clientWidth + 1) siteHeader.classList.add('is-compact');
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
  var aiStage = $('[data-ai-stage]');
  $$('[data-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      mainImage.src = thumb.dataset.thumb;
      if (aiStage) aiStage.hidden = true;
      $$('[data-thumb], [data-ai-show]').forEach(function (t) { t.classList.remove('active'); });
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
    var aiLocked = !!$('[data-ai-box]', form);
    var baseSwapped = false;
    var useBaseImage = function () {
      if (!baseSwapped && stage && stage.dataset.previewBase && mainImage) {
        mainImage.src = stage.dataset.previewBase;
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
      var c = $('[data-selected-color]'); if (c) c.textContent = color || '';
      var s = $('[data-selected-size]'); if (s) s.textContent = size || '';
      if (match) {
        variantInput.value = match.id;
        priceEls.forEach(function (el) { el.textContent = match.priceLabel; });
        if (match.image && mainImage && !baseSwapped) mainImage.src = match.image;
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

      var showStage = function () {
        aiStage.hidden = false;
        $$('[data-thumb], [data-ai-show]').forEach(function (t) { t.classList.remove('active'); });
        if (showBtn) showBtn.classList.add('active');
      };
      if (showBtn) showBtn.addEventListener('click', showStage);

      var setLocked = function (locked) {
        aiLocked = locked;
        addBtn.disabled = locked;
        addBtn.textContent = locked ? 'Créez son portrait \u2191' : addLabel;
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
        genBtn.disabled = !(aiPhoto.files && aiPhoto.files[0] && aiConsent.checked);
      };
      var updateRemaining = function (n) {
        if (typeof n !== 'number' || !remainingEl) return;
        remainingEl.textContent = n > 0
          ? 'Environ 30 secondes · ' + n + ' création' + (n > 1 ? 's' : '') + ' offerte' + (n > 1 ? 's' : '') + ' aujourd’hui'
          : 'C’était votre dernière création offerte du jour ♡';
      };

      aiPhoto.addEventListener('change', function () {
        var file = aiPhoto.files && aiPhoto.files[0];
        aiError.hidden = true;
        if (file && file.size > 20 * 1024 * 1024) {
          aiPhoto.value = '';
          fail('Votre photo est trop lourde (20 Mo maximum).');
          return;
        }
        if (file) {
          var thumbEl = $('[data-photo-thumb]', aiBox);
          thumbEl.style.backgroundImage = 'url("' + URL.createObjectURL(file) + '")';
          thumbEl.hidden = false;
          $('[data-photo-drop]', aiBox).classList.add('has-photo');
          $('[data-photo-label]', aiBox).textContent = 'Photo ajoutée ✓ (cliquez pour changer)';
        }
        updateGenerate();
      });
      aiConsent.addEventListener('change', updateGenerate);
      var aiDrop = $('[data-photo-drop]', aiBox);
      ['dragenter', 'dragover'].forEach(function (ev) { aiDrop.addEventListener(ev, function () { aiDrop.classList.add('dragover'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { aiDrop.addEventListener(ev, function () { aiDrop.classList.remove('dragover'); }); });

      var steps = ['Analyse de la photo…', 'Esquisse de la tête et des oreilles…', 'Tracé à l’encre bordeaux…', 'Lavis d’aquarelle poudrée…', 'Derniers détails dans le regard…', 'Encore quelques secondes, on peaufine…'];
      var stepTimer = null;
      var startProgress = function () {
        var i = 0;
        startBox.hidden = true;
        resultBox.hidden = true;
        aiError.hidden = true;
        progress.hidden = false;
        progressText.textContent = steps[0];
        bar.style.transition = 'none';
        bar.style.width = '3%';
        void bar.offsetWidth;
        bar.style.transition = 'width 45s cubic-bezier(.2,.6,.3,1)';
        bar.style.width = '92%';
        clearInterval(stepTimer);
        stepTimer = setInterval(function () { i = Math.min(steps.length - 1, i + 1); progressText.textContent = steps[i]; }, 7000);
      };
      var ready = function (state) {
        clearInterval(stepTimer);
        bar.style.transition = 'width .3s';
        bar.style.width = '100%';
        hasOwnPortrait = true;
        tokenInput.value = state.token;
        aiThumb.src = state.preview;
        setArt(state.preview);
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
        track('ai_portrait_ready');
      };
      var poll = function (token, started) {
        if (Date.now() - started > 4 * 60 * 1000) { clearInterval(stepTimer); fail('Le dessin prend plus de temps que prévu. Réessayez dans un instant.'); return; }
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
        else if (state.status === 'failed') { clearInterval(stepTimer); fail(state.message + (state.admin ? '\n\n🔧 Diagnostic (visible car vous êtes connectée à l\u2019administration) :\n' + state.admin : '')); }
        else poll(state.token, started);
      };

      // Photo réduite à 2400 px dans le navigateur : envoi rapide en 4G, sans buter sur les limites du serveur
      var shrink = function (file) {
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

      genBtn.addEventListener('click', function () {
        var file = aiPhoto.files && aiPhoto.files[0];
        if (!file || !aiConsent.checked) return;
        genBtn.disabled = true;
        startProgress();
        showStage();
        track('ai_portrait_start');
        shrink(file).then(function (photo) {
          var data = new FormData();
          data.append('_token', $('input[name="_token"]', form).value);
          data.append('product_id', aiBox.dataset.productId);
          data.append('consent', '1');
          data.append('photo', photo);
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
        updateGenerate();
        aiPhoto.focus();
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
          var target = (aiLocked && $('[data-ai-photo]', form)) || photoInput || persoInput;
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
