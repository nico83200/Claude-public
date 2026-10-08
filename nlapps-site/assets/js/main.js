/* NLapps — interactions légères (aucune dépendance) */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  document.addEventListener('DOMContentLoaded', function () {
    var header = document.querySelector('.site-header');
    var nav = document.getElementById('menu');
    var toggle = document.querySelector('.menu-toggle');

    /* Header : ombre au défilement */
    if (header) {
      var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* Menu mobile */
    function setMenu(open) {
      if (!nav || !toggle) return;
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
      var use = toggle.querySelector('use');
      if (use) use.setAttribute('href', open ? '#i-close' : '#i-menu');
    }
    if (toggle) {
      toggle.addEventListener('click', function () { setMenu(!nav.classList.contains('is-open')); });
      nav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    }

    /* Apparition des blocs au défilement */
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      reveals.forEach(function (el) { io.observe(el); });
    } else {
      reveals.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* Lien actif dans le menu */
    var links = nav ? nav.querySelectorAll('a[href^="#"]') : [];
    var sections = Array.prototype.map.call(links, function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    if ('IntersectionObserver' in window && sections.length) {
      var spy = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          links.forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('href') === '#' + entry.target.id); });
        });
      }, { rootMargin: '-45% 0px -50% 0px' });
      sections.forEach(function (s) { spy.observe(s); });
    }

    /* CTA « Demander une démonstration » : pré-sélectionne l'objet du formulaire */
    var form = document.getElementById('contact-form');
    document.querySelectorAll('[data-subject="demo"]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!form) return;
        var radio = form.querySelector('input[name="objet"][value="Démonstration Approvia"]');
        if (radio) radio.checked = true;
        if (el.tagName === 'BUTTON') {
          e.preventDefault();
          form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        setTimeout(function () { var f = form.querySelector('#f-nom'); if (f) f.focus({ preventScroll: true }); }, 500);
      });
    });

    /* Formulaire de contact */
    if (form) {
      var status = form.querySelector('.form-status');
      var messages = {
        valueMissing: 'Ce champ est requis.',
        typeMismatch: 'Adresse email invalide.'
      };
      var showError = function (input) {
        var field = input.closest('.field');
        var err = field.querySelector('.field-error');
        if (input.validity.valid) {
          field.classList.remove('has-error');
          input.removeAttribute('aria-invalid');
          if (err) err.remove();
          return true;
        }
        field.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        if (!err) {
          err = document.createElement('span');
          err.className = 'field-error';
          err.id = input.id + '-err';
          input.setAttribute('aria-describedby', err.id);
          field.appendChild(err);
        }
        err.textContent = input.validity.typeMismatch ? messages.typeMismatch : messages.valueMissing;
        return false;
      };
      form.querySelectorAll('input:not([type="radio"]), textarea').forEach(function (input) {
        input.addEventListener('blur', function () { if (input.value) showError(input); });
        input.addEventListener('input', function () { if (input.closest('.has-error')) showError(input); });
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var inputs = form.querySelectorAll('input:not([type="radio"]), textarea');
        var firstInvalid = null;
        inputs.forEach(function (input) { if (!showError(input) && !firstInvalid) firstInvalid = input; });
        if (firstInvalid) {
          status.className = 'form-status err';
          status.textContent = 'Merci de compléter les champs indiqués.';
          firstInvalid.focus();
          return;
        }

        var data = new FormData(form);
        var endpoint = form.getAttribute('data-endpoint');
        var btn = form.querySelector('button[type="submit"]');

        if (endpoint) {
          btn.disabled = true;
          status.className = 'form-status';
          status.textContent = 'Envoi en cours…';
          fetch(endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (r) {
              if (!r.ok) throw new Error();
              form.reset();
              status.className = 'form-status ok';
              status.textContent = 'Merci ! Votre demande a bien été envoyée. Nous revenons vers vous rapidement.';
            })
            .catch(function () {
              status.className = 'form-status err';
              status.textContent = "L'envoi a échoué. Vous pouvez nous écrire directement à " + form.getAttribute('data-mailto') + '.';
            })
            .finally(function () { btn.disabled = false; });
          return;
        }

        /* Repli : ouverture du logiciel de messagerie */
        var body = [
          'Objet : ' + data.get('objet'),
          'Nom : ' + data.get('prenom') + ' ' + data.get('nom'),
          'Entreprise : ' + data.get('entreprise'),
          'Email : ' + data.get('email'),
          'Téléphone : ' + (data.get('telephone') || '—'),
          '',
          data.get('projet')
        ].join('\n');
        var subject = data.get('objet') + ' — ' + data.get('entreprise');
        window.location.href = 'mailto:' + form.getAttribute('data-mailto') +
          '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        status.className = 'form-status ok';
        status.textContent = 'Votre messagerie va s\'ouvrir avec votre demande pré-remplie.';
      });
    }

    /* Bulle d'aide — Assistance NLapps */
    var help = document.getElementById('help-widget');
    if (help) {
      var panel = help.querySelector('.help-panel');
      var launcher = help.querySelector('.help-launcher');
      var teaser = help.querySelector('.help-teaser');
      var KEY = 'nlapps-help-seen';
      var store = {
        get: function () { try { return sessionStorage.getItem(KEY); } catch (e) { return null; } },
        set: function () { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }
      };
      var assistUrl = help.getAttribute('data-assist-url');
      if (assistUrl) help.querySelectorAll('[data-assist-link]').forEach(function (a) { a.href = assistUrl; });

      var setHelp = function (open, focus) {
        panel.hidden = !open;
        teaser.hidden = true;
        help.classList.toggle('is-open', open);
        help.classList.remove('has-dot');
        launcher.setAttribute('aria-expanded', String(open));
        launcher.setAttribute('aria-label', open ? "Fermer l'assistance NLapps" : "Ouvrir l'assistance NLapps");
        if (open) { store.set(); if (focus) panel.querySelector('.help-action').focus(); }
        else if (focus) launcher.focus();
      };
      launcher.addEventListener('click', function () { setHelp(panel.hidden, true); });
      teaser.addEventListener('click', function () { setHelp(true, true); });
      help.querySelector('.help-close').addEventListener('click', function () { setHelp(false, true); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setHelp(false, true); });
      panel.querySelectorAll('a[href^="#"]').forEach(function (a) { a.addEventListener('click', function () { setHelp(false); }); });

      /* Ouverture automatique : une fois par session, sans interrompre une saisie */
      if (!store.get()) {
        var delay = parseInt(help.getAttribute('data-delay'), 10) || 6000;
        setTimeout(function () {
          if (store.get() || !panel.hidden) return;
          var active = document.activeElement;
          if (active && /INPUT|TEXTAREA|SELECT/.test(active.tagName)) return;
          store.set();
          if (window.matchMedia('(min-width: 600px)').matches) {
            setHelp(true, false);
          } else {
            /* Sur mobile : simple invitation, le panneau ne masque pas la page */
            teaser.hidden = false;
            help.classList.add('has-dot');
            setTimeout(function () { teaser.hidden = true; }, 8000);
          }
        }, delay);
      }
    }

    var year = document.querySelector('[data-year]');
    if (year) year.textContent = new Date().getFullYear();
  });
})();
