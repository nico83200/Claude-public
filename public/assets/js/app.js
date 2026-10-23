/* Crinière & Co — interactions boutique (vanilla JS, sans dépendance) */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  // Menu mobile
  var toggle = $('.nav-toggle');
  var nav = $('#main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Soumission automatique (tri, quantités du panier)
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form && el.form.submit(); });
  });

  // Boutons +/- de quantité
  $$('[data-qty]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentNode.querySelector('input');
      var v = Math.min(20, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.qty, 10)));
      input.value = v;
    });
  });

  // Galerie
  var mainImage = $('[data-main-image]');
  $$('[data-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      mainImage.src = thumb.dataset.thumb;
      $$('[data-thumb]').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });

  // Sélection de variante (couleur × taille)
  var form = $('[data-buy-form]');
  if (form) {
    var variants = JSON.parse(($('[data-variants]', form) || {}).textContent || '[]');
    var variantInput = $('[data-variant-input]', form);
    var priceEl = $('[data-price]');
    var addBtn = $('[data-add-button]', form);
    var unavailable = $('[data-unavailable]', form);

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

      // Désactive les tailles qui n'existent pas dans la couleur choisie
      $$('input[data-option="size"]', form).forEach(function (input) {
        input.disabled = color !== null && !variants.some(function (v) { return v.color === color && v.size === input.value; });
      });

      var c = $('[data-selected-color]'); if (c) c.textContent = color || '';
      var s = $('[data-selected-size]'); if (s) s.textContent = size || '';

      if (match) {
        variantInput.value = match.id;
        priceEl.textContent = match.priceLabel;
        if (match.image && mainImage) mainImage.src = match.image;
        addBtn.disabled = false;
        unavailable.hidden = true;
      } else {
        addBtn.disabled = true;
        unavailable.hidden = false;
      }
    };
    $$('input[data-option]', form).forEach(function (input) { input.addEventListener('change', sync); });
    sync();
  }

  // Aperçu de personnalisation en direct
  var persoInput = $('[data-personalization]');
  var previewText = $('[data-preview-text]');
  var charCount = $('[data-char-count]');
  if (persoInput) {
    var update = function () {
      var clean = persoInput.value.replace(/[^\p{L}\p{N} '’&.\-]/gu, '');
      if (clean !== persoInput.value) persoInput.value = clean;
      if (previewText) previewText.textContent = clean;
      if (charCount) charCount.textContent = clean.length;
    };
    persoInput.addEventListener('input', update);
    update();
  }

  // Consentement cookies + chargement conditionnel GA4 / Meta Pixel
  var CONSENT_KEY = 'cc_consent';
  var banner = $('[data-cookie-banner]');
  var getConsent = function () { try { return localStorage.getItem(CONSENT_KEY); } catch (e) { return null; } };
  var setConsent = function (v) { try { localStorage.setItem(CONSENT_KEY, v); } catch (e) {} };

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
    var consent = getConsent();
    if (consent === 'accept') loadAnalytics();
    if (!consent && hasTrackers) banner.hidden = false;
    $$('[data-cookie-choice]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setConsent(btn.dataset.cookieChoice);
        banner.hidden = true;
        if (btn.dataset.cookieChoice === 'accept') loadAnalytics();
        else if (getConsent() === 'refuse' && window.gtag) location.reload();
      });
    });
    $$('[data-cookie-settings]').forEach(function (btn) {
      btn.addEventListener('click', function () { banner.hidden = false; });
    });
  }
})();
