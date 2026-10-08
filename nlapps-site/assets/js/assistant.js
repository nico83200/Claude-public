/* ==========================================================================
   Assistant NLapps — conversation intégrée au site
   - Répond aux questions fréquentes sur NLapps et Approvia (base ci-dessous).
   - Recueille les demandes de démo / projet directement dans la conversation.
   - Si data-chat-endpoint est renseigné, les messages libres sont envoyés à ce
     moteur (POST JSON { message, history } → { reply, quick? }) ; en cas
     d'erreur, l'assistant revient aux réponses intégrées.
   ========================================================================== */
(function () {
  'use strict';

  /* ---------- Base de réponses (modifiable) ---------- */
  var MENU = [
    { label: 'Découvrir Approvia', to: 'approvia' },
    { label: 'Demander une démo', to: 'demo' },
    { label: 'Un projet sur mesure', to: 'projet' },
    { label: 'Qui est NLapps ?', to: 'nlapps' }
  ];

  var INTENTS = {
    accueil: {
      say: ["Bonjour ! Je suis l'assistant <b>NLapps</b>.",
            "Je peux vous présenter Approvia, organiser une démonstration ou recueillir votre projet. Que puis-je faire pour vous ?"],
      quick: MENU
    },
    approvia: {
      match: /approvia|achat|commande|approvisionn|fournisseur|logiciel/,
      say: ["<b>Approvia</b> centralise tout le processus d'achat dans une seule plateforme :",
            "<ul><li>Demande</li><li>Validation</li><li>Commande</li><li>Réception</li><li>Stock</li><li>Budget</li></ul>Vos équipes commandent simplement, vos responsables gardent le contrôle sur les dépenses, les stocks et les budgets."],
      quick: [{ label: 'Les fonctionnalités', to: 'fonctionnalites' }, { label: 'Plusieurs sites ?', to: 'multisite' }, { label: 'Demander une démo', to: 'demo' }]
    },
    fonctionnalites: {
      match: /fonctionnalit|fonction|module|que fait|possibilit/,
      say: ["Approvia couvre tout le cycle d'achat :",
            "<ul><li>Catalogue produits, fournisseurs et tarifs</li><li>Demandes d'achat</li><li>Circuit de validation</li><li>Commandes fournisseurs</li><li>Réception et suivi des écarts</li><li>Stocks</li><li>Budgets et suivi des dépenses</li><li>Multi-sites</li><li>Traçabilité de chaque étape</li></ul>"],
      quick: [{ label: 'La validation', to: 'validation' }, { label: 'Les budgets', to: 'budget' }, { label: 'Demander une démo', to: 'demo' }]
    },
    multisite: {
      match: /multi|plusieurs (sites|etablissements|centres|agences)|site|etablissement|reseau|groupe|centre/,
      say: ["Oui, Approvia est pensé pour les organisations <b>multi-sites</b> : réseaux d'établissements, groupes, centres de santé…",
            "Chaque site passe ses demandes, et la direction dispose d'une vision consolidée des dépenses, des budgets et des besoins de tous les établissements."],
      quick: [{ label: 'Les budgets', to: 'budget' }, { label: 'Demander une démo', to: 'demo' }]
    },
    validation: {
      match: /valid|circuit|approbat|workflow|responsable/,
      say: ["Les demandes suivent le <b>circuit de validation défini par votre organisation</b>.",
            "Chaque responsable voit les demandes à traiter, valide ou refuse en un clic, et chaque décision est enregistrée : qui a demandé, validé, commandé ou réceptionné."],
      quick: [{ label: 'La traçabilité', to: 'tracabilite' }, { label: 'Demander une démo', to: 'demo' }]
    },
    budget: {
      match: /budget|depense|cout engage|pilotage|piloter|tableau de bord|dashboard|reporting/,
      say: ["Les responsables et la direction suivent les <b>dépenses engagées</b> face aux <b>budgets</b>, par site ou par service.",
            "L'objectif : accéder aux bonnes données au bon moment, sans compiler de fichiers Excel."],
      quick: [{ label: 'Les stocks', to: 'stock' }, { label: 'Demander une démo', to: 'demo' }]
    },
    stock: {
      match: /stock|inventaire|reception|livraison|ecart/,
      say: ["À la réception, vos équipes enregistrent les produits reçus et signalent les écarts éventuels.",
            "Approvia conserve ainsi une vision des <b>produits disponibles</b> et des <b>besoins</b> à venir."],
      quick: [{ label: 'Les fonctionnalités', to: 'fonctionnalites' }, { label: 'Demander une démo', to: 'demo' }]
    },
    tracabilite: {
      match: /trac|historique|qui a|audit/,
      say: ["Chaque étape est enregistrée : <b>qui</b> a demandé, validé, commandé ou réceptionné, et <b>quand</b>. Plus besoin de chercher dans les emails."],
      quick: [{ label: 'Demander une démo', to: 'demo' }, { label: 'Autre question', to: 'menu' }]
    },
    prix: {
      match: /prix|tarif|cout|combien|abonnement|licence|devis/,
      say: ["Le tarif d'Approvia dépend de votre organisation (nombre de sites, d'utilisateurs, besoins).",
            "Le plus simple : une courte démonstration, puis une proposition adaptée. Voulez-vous que je transmette votre demande ?"],
      quick: [{ label: 'Oui, demander une démo', to: 'demo' }, { label: 'Plus tard', to: 'menu' }]
    },
    projet: {
      match: /sur mesure|specifique|projet|developp|application|digitalis|automatis|processus|excel/,
      say: ["NLapps conçoit aussi des <b>applications sur mesure</b> pour simplifier un processus métier.",
            "Notre méthode : <b>Comprendre</b> votre fonctionnement réel, <b>Concevoir</b> une expérience simple, <b>Digitaliser</b> le processus, puis <b>Améliorer</b> la solution dans le temps.",
            "Voulez-vous nous décrire votre projet ? Je le transmets à l'équipe."],
      quick: [{ label: 'Décrire mon projet', to: 'lead_projet' }, { label: 'Découvrir Approvia', to: 'approvia' }]
    },
    nlapps: {
      match: /nlapps|qui etes|entreprise|societe|a propos|equipe/,
      say: ["<b>NLapps</b> est une entreprise française à taille humaine qui conçoit des solutions numériques métier.",
            "Notre conviction : la technologie doit simplifier le travail, pas le compliquer. Approvia est la première solution de notre gamme."],
      quick: [{ label: 'Découvrir Approvia', to: 'approvia' }, { label: 'Un projet sur mesure', to: 'projet' }]
    },
    securite: {
      match: /secur|donnees|rgpd|heberg|confidential|sauvegarde/,
      say: ["Bonne question. L'hébergement et la sécurité des données font partie des points que nous détaillons avec chaque client.",
            "Souhaitez-vous qu'un membre de l'équipe vous recontacte à ce sujet ?"],
      quick: [{ label: 'Être recontacté', to: 'lead_contact' }, { label: 'Autre question', to: 'menu' }]
    },
    demo: {
      match: /demo|demonstration|essai|essayer|tester|voir l.app|presentation/,
      say: ["Avec plaisir ! Une démonstration dure environ <b>30 minutes</b>, à partir de vos cas d'usage.",
            "Je prends quelques informations et l'équipe revient vers vous rapidement."],
      next: 'lead_demo'
    },
    contact: {
      match: /contact|rappel|rappeler|appel|telephone|humain|conseiller|parler a|joindre|email|mail/,
      say: ["Bien sûr, je transmets votre demande à l'équipe NLapps."],
      next: 'lead_contact'
    },
    merci: {
      match: /^(merci|super|parfait|top|genial|ok|d.accord)\b/,
      say: ["Avec plaisir ! Autre chose ?"],
      quick: MENU
    },
    bonjour: {
      match: /^(bonjour|salut|hello|bonsoir|coucou)\b/,
      say: ["Bonjour ! Comment puis-je vous aider ?"],
      quick: MENU
    },
    menu: {
      say: ["Que souhaitez-vous savoir ?"],
      quick: MENU
    },
    inconnu: {
      say: ["Je n'ai pas encore la réponse à cette question, mais l'équipe NLapps l'aura.",
            "Voulez-vous que je lui transmette votre message ?"],
      quick: [{ label: 'Oui, transmettre', to: 'lead_contact' }, { label: 'Voir les sujets', to: 'menu' }]
    }
  };
  var ORDER = ['merci', 'bonjour', 'demo', 'prix', 'securite', 'contact', 'tracabilite', 'validation', 'stock', 'budget', 'fonctionnalites', 'multisite', 'projet', 'nlapps', 'approvia'];

  /* Étapes de la prise de demande */
  var LEAD_STEPS = [
    { key: 'nom', ask: 'Quel est votre <b>nom et prénom</b> ?' },
    { key: 'entreprise', ask: 'Pour quelle <b>entreprise ou organisation</b> ?' },
    { key: 'email', ask: 'À quelle <b>adresse email</b> pouvons-nous vous répondre ?', check: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }, error: "Cette adresse email ne semble pas valide. Pouvez-vous vérifier ?" },
    { key: 'telephone', ask: 'Un <b>téléphone</b> pour vous joindre ? (facultatif)', optional: true },
    { key: 'projet', askBy: {
        demo: "En quelques mots, votre contexte ? (nombre de sites, d'utilisateurs, outils actuels…)",
        projet: 'Décrivez-nous le <b>processus à simplifier</b> : qui l\'utilise, comment il fonctionne aujourd\'hui…',
        contact: 'Quel est votre <b>message</b> pour l\'équipe ?'
      } }
  ];
  var LEAD_LABEL = { demo: 'Démonstration Approvia', projet: 'Projet', contact: 'Contact' };

  /* ---------- Utilitaires ---------- */
  var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var norm = function (t) { return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[’']/g, "'"); };
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var KEY = 'nlapps-chat', SEEN = 'nlapps-chat-seen';
  var store = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('chat-widget');
    if (!root) return;
    var panel = root.querySelector('.chat-panel');
    var log = root.querySelector('.chat-log');
    var quickBox = root.querySelector('.chat-quick');
    var form = root.querySelector('.chat-input');
    var input = form.querySelector('input');
    var launcher = root.querySelector('.chat-launcher');
    var teaser = root.querySelector('.chat-teaser');
    var endpoint = root.getAttribute('data-chat-endpoint');
    var contactForm = document.getElementById('contact-form');

    /* État de la conversation (conservé pendant la session) */
    var state = { messages: [], quick: [], lead: null };
    try { var saved = JSON.parse(store.get(KEY)); if (saved && saved.messages) state = saved; } catch (e) {}
    var save = function () { store.set(KEY, JSON.stringify(state)); };

    var scroll = function () { log.scrollTop = log.scrollHeight; };
    var render = function (m) {
      var el = document.createElement('div');
      el.className = 'msg msg-' + m.from;
      el.innerHTML = m.html;
      log.appendChild(el);
    };
    var renderQuick = function () {
      quickBox.innerHTML = '';
      state.quick.forEach(function (q) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'chat-chip'; b.textContent = q.label;
        b.addEventListener('click', function () { userSays(q.label, q.to); });
        quickBox.appendChild(b);
      });
    };
    var setQuick = function (q) { state.quick = q || []; renderQuick(); save(); };

    /* Messages du bot, avec indicateur de saisie */
    var queue = Promise.resolve();
    var botSays = function (lines, quick) {
      setQuick([]);
      lines.forEach(function (html) {
        queue = queue.then(function () {
          return new Promise(function (resolve) {
            var typing = document.createElement('div');
            typing.className = 'msg msg-bot msg-typing';
            typing.innerHTML = '<i></i><i></i><i></i>';
            log.appendChild(typing); scroll();
            var wait = reduced ? 0 : Math.min(1400, 450 + html.replace(/<[^>]+>/g, '').length * 9);
            setTimeout(function () {
              typing.remove();
              var m = { from: 'bot', html: html };
              state.messages.push(m); render(m); scroll(); save();
              resolve();
            }, wait);
          });
        });
      });
      queue = queue.then(function () { setQuick(quick); scroll(); });
      return queue;
    };

    var userSays = function (text, to) {
      var m = { from: 'user', html: esc(text) };
      state.messages.push(m); render(m); setQuick([]); scroll(); save();
      if (to) run(to); else handle(text);
    };

    /* Intentions */
    var run = function (id) {
      if (id.indexOf('lead_') === 0) return startLead(id.slice(5));
      var it = INTENTS[id] || INTENTS.inconnu;
      var p = botSays(it.say, it.quick);
      if (it.next) p.then(function () { startLead(it.next.slice(5)); });
    };
    var detect = function (text) {
      var t = norm(text);
      for (var i = 0; i < ORDER.length; i++) {
        if (!INTENTS[ORDER[i]].match.test(t)) continue;
        /* « Approvia » cité explicitement l'emporte sur l'intention générique « projet » */
        return ORDER[i] === 'projet' && /approvia/.test(t) ? 'approvia' : ORDER[i];
      }
      return 'inconnu';
    };

    var handle = function (text) {
      if (state.lead) return leadAnswer(text);
      if (endpoint) {
        fetch(endpoint, {
          method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ message: text, history: state.messages.slice(-12) })
        }).then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
          .then(function (d) {
            if (!d || !d.reply) throw new Error();
            botSays([].concat(d.reply).map(esc), d.quick);
          })
          .catch(function () { run(detect(text)); });
        return;
      }
      run(detect(text));
    };

    /* Prise de demande dans la conversation */
    var askStep = function () {
      var step = LEAD_STEPS[state.lead.step];
      var q = step.askBy ? step.askBy[state.lead.type] : step.ask;
      var quick = [{ label: 'Annuler', to: 'lead_cancel' }];
      if (step.optional) quick.unshift({ label: 'Passer', to: 'lead_skip' });
      botSays([q], quick).then(function () { input.focus({ preventScroll: true }); });
    };
    var startLead = function (type) {
      if (type === 'cancel') { state.lead = null; save(); return botSays(['Pas de souci, la demande est annulée. Autre chose ?'], MENU); }
      if (type === 'skip') { return leadAnswer(''); }
      if (type === 'send') { return sendLead(); }
      if (type === 'restart') { type = state.lead ? state.lead.type : 'contact'; }
      state.lead = { type: type, step: 0, data: {} }; save();
      askStep();
    };
    var leadAnswer = function (text) {
      var lead = state.lead;
      if (lead.step >= LEAD_STEPS.length) return; /* en attente de confirmation */
      var step = LEAD_STEPS[lead.step];
      var v = String(text).trim();
      if (!v && !step.optional) return botSays(['Il me manque cette information pour transmettre votre demande.'], [{ label: 'Annuler', to: 'lead_cancel' }]);
      if (v && step.check && !step.check(v)) return botSays([step.error], [{ label: 'Annuler', to: 'lead_cancel' }]);
      lead.data[step.key] = v; lead.step++; save();
      if (lead.step < LEAD_STEPS.length) return askStep();
      var d = lead.data;
      botSays(['Merci ! Voici le récapitulatif :' +
        '<div class="msg-card"><b>' + esc(LEAD_LABEL[lead.type]) + '</b>' +
        '<span>' + esc(d.nom) + ' · ' + esc(d.entreprise) + '</span>' +
        '<span>' + esc(d.email) + (d.telephone ? ' · ' + esc(d.telephone) : '') + '</span>' +
        '<span>« ' + esc(d.projet) + ' »</span></div>', 'Je l\'envoie à l\'équipe ?'],
        [{ label: 'Envoyer ma demande', to: 'lead_send' }, { label: 'Recommencer', to: 'lead_restart' }, { label: 'Annuler', to: 'lead_cancel' }]);
    };
    var sendLead = function () {
      var lead = state.lead; if (!lead) return;
      var d = lead.data;
      var fd = new FormData();
      fd.append('objet', LEAD_LABEL[lead.type]);
      fd.append('nom', d.nom); fd.append('prenom', '');
      fd.append('entreprise', d.entreprise); fd.append('email', d.email);
      fd.append('telephone', d.telephone || ''); fd.append('projet', d.projet);
      fd.append('source', 'Assistant NLapps');
      var formEndpoint = contactForm && contactForm.getAttribute('data-endpoint');
      var mailto = (contactForm && contactForm.getAttribute('data-mailto')) || 'contact@nlapps.fr';
      var done = function (html) { state.lead = null; save(); botSays([html], [{ label: 'Autre question', to: 'menu' }]); };

      if (formEndpoint) {
        botSays([]);
        fetch(formEndpoint, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
          .then(function (r) { if (!r.ok) throw new Error(); done("C'est envoyé ! L'équipe NLapps revient vers vous très rapidement à l'adresse <b>" + esc(d.email) + '</b>.'); })
          .catch(function () { done("L'envoi n'a pas abouti. Vous pouvez nous écrire directement à <a href=\"mailto:" + esc(mailto) + '">' + esc(mailto) + '</a>.'); });
        return;
      }
      var body = ['Objet : ' + LEAD_LABEL[lead.type], 'Nom : ' + d.nom, 'Entreprise : ' + d.entreprise, 'Email : ' + d.email,
        'Téléphone : ' + (d.telephone || '—'), '', d.projet, '', '— Envoyé depuis l\'assistant du site NLapps'].join('\n');
      window.location.href = 'mailto:' + mailto + '?subject=' + encodeURIComponent(LEAD_LABEL[lead.type] + ' — ' + d.entreprise) + '&body=' + encodeURIComponent(body);
      done('Votre messagerie s\'ouvre avec la demande pré-remplie : il ne reste plus qu\'à l\'envoyer. Merci !');
    };

    /* Saisie libre */
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var t = input.value.trim();
      var step = state.lead && LEAD_STEPS[state.lead.step];
      if (!t && !(step && step.optional)) return;
      input.value = '';
      if (t) userSays(t); else userSays('Passer', 'lead_skip');
    });

    /* Ouverture / fermeture */
    var started = false;
    var start = function () {
      if (started) return; started = true;
      if (state.messages.length) { state.messages.forEach(render); renderQuick(); scroll(); }
      else run('accueil');
    };
    var setOpen = function (open, focus) {
      panel.hidden = !open; teaser.hidden = true;
      root.classList.toggle('is-open', open);
      root.classList.remove('has-dot');
      launcher.setAttribute('aria-expanded', String(open));
      launcher.setAttribute('aria-label', open ? "Fermer la conversation" : "Ouvrir la conversation avec l'assistant NLapps");
      if (open) { store.set(SEEN, '1'); start(); scroll(); if (focus) input.focus({ preventScroll: true }); }
      else if (focus) launcher.focus();
    };
    launcher.addEventListener('click', function () { setOpen(panel.hidden, true); });
    teaser.addEventListener('click', function () { setOpen(true, true); });
    root.querySelector('.chat-close').addEventListener('click', function () { setOpen(false, true); });
    root.querySelector('.chat-reset').addEventListener('click', function () {
      state = { messages: [], quick: [], lead: null }; store.del(KEY);
      log.innerHTML = ''; renderQuick(); queue = Promise.resolve(); run('accueil'); input.focus({ preventScroll: true });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false, true); });

    /* Ouverture automatique : une fois par session, sans interrompre une saisie */
    if (!store.get(SEEN)) {
      var delay = parseInt(root.getAttribute('data-delay'), 10) || 6000;
      setTimeout(function () {
        if (store.get(SEEN) || !panel.hidden) return;
        var a = document.activeElement;
        if (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName)) return;
        store.set(SEEN, '1');
        if (window.matchMedia('(min-width: 600px)').matches) {
          setOpen(true, false);
        } else {
          teaser.hidden = false; root.classList.add('has-dot');
          setTimeout(function () { teaser.hidden = true; }, 8000);
        }
      }, delay);
    }
  });
})();
