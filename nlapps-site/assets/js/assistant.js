/* ==========================================================================
   Assistant NLapps — même parcours que dans Approvia (voir INTEGRATION.md) :
   1. l'assistant répond d'abord (base de réponses ci-dessous) ;
   2. « Parler à un conseiller » ouvre une conversation avec le centre
      d'assistance NLapps (open → send → poll toutes les 4 s avec `after`),
      en transmettant la transcription de l'assistant ;
   3. statuts open / pending / closed, images jointes, note de satisfaction.
   Le navigateur ne parle qu'au relais serveur (data-relay, support/chat.php) :
   ni la clé API ni le jeton de conversation n'arrivent ici.
   ========================================================================== */
(function () {
  'use strict';

  /* ---------- Base de réponses de l'assistant (modifiable) ---------- */
  var AGENT_CHIP = { label: 'Parler à un conseiller', to: 'conseiller' };
  var MENU = [
    { label: 'Découvrir Approvia', to: 'approvia' },
    { label: 'Demander une démo', to: 'demo' },
    { label: 'Un projet sur mesure', to: 'projet' },
    AGENT_CHIP
  ];

  var INTENTS = {
    accueil: {
      say: ["Bonjour ! Je suis l'assistant <b>NLapps</b>.",
            "Je peux vous présenter Approvia, organiser une démonstration, recueillir votre projet ou vous mettre en relation avec un conseiller. Que puis-je faire pour vous ?"],
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
      quick: [{ label: 'Oui, demander une démo', to: 'demo' }, AGENT_CHIP]
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
            "Un conseiller peut vous répondre précisément."],
      quick: [AGENT_CHIP, { label: 'Autre question', to: 'menu' }]
    },
    demo: {
      match: /demo|demonstration|essai|essayer|tester|voir l.app|presentation/,
      say: ["Avec plaisir ! Une démonstration dure environ <b>30 minutes</b>, à partir de vos cas d'usage.",
            "Je prends quelques informations et l'équipe revient vers vous rapidement."],
      next: 'lead_demo'
    },
    conseiller: {
      match: /conseiller|humain|une personne|quelqu.un|parler a|joindre|contact|rappel|appel|telephone|support|assistance|aide/,
      next: 'agent'
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
    menu: { say: ["Que souhaitez-vous savoir ?"], quick: MENU },
    inconnu: {
      say: ["Je n'ai pas encore la réponse à cette question, mais l'équipe NLapps l'aura."],
      quick: [AGENT_CHIP, { label: 'Voir les sujets', to: 'menu' }]
    }
  };
  var ORDER = ['merci', 'bonjour', 'demo', 'prix', 'securite', 'conseiller', 'tracabilite', 'validation', 'stock', 'budget', 'fonctionnalites', 'multisite', 'projet', 'nlapps', 'approvia'];

  /* Questions posées dans la conversation (une à la fois) */
  var isEmail = function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); };
  var STEPS = {
    nom: { ask: 'Quel est votre <b>nom et prénom</b> ?', profile: 'name' },
    entreprise: { ask: 'Pour quelle <b>entreprise ou organisation</b> ?', profile: 'company' },
    email: { ask: 'À quelle <b>adresse email</b> pouvons-nous vous répondre ?', profile: 'email', check: isEmail },
    email_opt: { ask: 'Votre <b>email</b> ? Nous vous y enverrons la transcription de l\'échange. (facultatif)', profile: 'email', check: isEmail, optional: true },
    telephone: { ask: 'Un <b>téléphone</b> pour vous joindre ? (facultatif)', optional: true },
    projet: { askBy: {
      demo: "En quelques mots, votre contexte ? (nombre de sites, d'utilisateurs, outils actuels…)",
      projet: 'Décrivez-nous le <b>processus à simplifier</b> : qui l\'utilise, comment il fonctionne aujourd\'hui…'
    } },
    message: { ask: 'C\'est noté. <b>Écrivez votre message</b> : un conseiller vous répond ici même.' }
  };
  var FLOWS = {
    demo: ['nom', 'entreprise', 'email', 'telephone', 'projet'],
    projet: ['nom', 'entreprise', 'email', 'telephone', 'projet'],
    agent: ['nom', 'email_opt', 'message']
  };
  var LEAD_LABEL = { demo: 'Démonstration Approvia', projet: 'Projet sur mesure' };

  /* ---------- Utilitaires ---------- */
  var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var norm = function (t) { return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[’']/g, "'"); };
  var plain = function (html) { var d = document.createElement('div'); d.innerHTML = html.replace(/<li>/g, '\n- '); return d.textContent.trim(); };
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var KEY = 'nlapps-chat', SEEN = 'nlapps-chat-seen';
  var store = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  };
  var blank = function () { return { messages: [], quick: [], flow: null, profile: {}, live: null, rating: false }; };

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('chat-widget');
    if (!root) return;
    var panel = root.querySelector('.chat-panel');
    var log = root.querySelector('.chat-log');
    var quickBox = root.querySelector('.chat-quick');
    var form = root.querySelector('.chat-input');
    var input = form.querySelector('#chat-text');
    var fileInput = form.querySelector('.chat-file');
    var launcher = root.querySelector('.chat-launcher');
    var teaser = root.querySelector('.chat-teaser');
    var stateLabel = root.querySelector('.chat-state');
    var relay = root.getAttribute('data-relay');
    var contactForm = document.getElementById('contact-form');

    /* Conseillers joignables ? null = inconnu, true/false après « status » */
    var advisors = relay ? null : false;
    var availability = null;

    var state = blank();
    try { var saved = JSON.parse(store.get(KEY)); if (saved && saved.messages) state = Object.assign(blank(), saved); } catch (e) {}
    var save = function () { store.set(KEY, JSON.stringify(state)); };
    var isOpen = function () { return !panel.hidden; };

    /* ---------- Relais serveur ---------- */
    var call = function (body) {
      var ctrl = window.AbortController ? new AbortController() : null;
      var timer = ctrl && setTimeout(function () { ctrl.abort(); }, 12000);
      return fetch(relay, {
        method: 'POST', credentials: 'same-origin', signal: ctrl && ctrl.signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body)
      }).then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (d) {
          if (timer) clearTimeout(timer);
          if (!r.ok) { var err = new Error(d.error || 'Erreur'); err.status = r.status; err.data = d; throw err; }
          return d;
        });
      }, function () { if (timer) clearTimeout(timer); var err = new Error("Connexion impossible avec l'assistance."); err.status = 0; err.data = {}; throw err; });
    };

    /* ---------- Affichage ---------- */
    var scroll = function () { log.scrollTop = log.scrollHeight; };
    var fileUrl = function (f) { return relay + (relay.indexOf('?') < 0 ? '?' : '&') + 'action=file&f=' + encodeURIComponent(f); };
    var render = function (m) {
      if (m.from === 'rating') return renderRating();
      var el = document.createElement('div');
      el.className = 'msg msg-' + m.from;
      if (m.html != null) el.innerHTML = m.html;
      else {
        var html = '';
        if (m.from === 'agent') html += '<span class="msg-author">' + esc(m.author || 'Conseiller NLapps') + '</span>';
        if (m.text) html += esc(m.text).replace(/\n/g, '<br>');
        if (m.file) html += '<img class="msg-img" loading="lazy" alt="Image jointe" src="' + esc(fileUrl(m.file)) + '">';
        if (m.local) html += '<img class="msg-img" alt="Image jointe" src="' + esc(m.local) + '">';
        el.innerHTML = html;
      }
      log.appendChild(el);
      return el;
    };
    var push = function (m) { var keep = Object.assign({}, m); if (keep.local) { delete keep.local; keep.text = keep.text || '📎 Image envoyée'; } state.messages.push(keep); save(); render(m); scroll(); };

    var menu = function (q) {
      return (q || []).filter(function (c) { return c.to !== 'conseiller' || advisors !== false; });
    };
    var renderQuick = function () {
      quickBox.innerHTML = '';
      var list = state.live ? [{ label: 'Terminer la conversation', to: 'agent_close' }] : menu(state.quick);
      list.forEach(function (q) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'chat-chip'; b.textContent = q.label;
        b.addEventListener('click', function () { userSays(q.label, q.to); });
        quickBox.appendChild(b);
      });
    };
    var setQuick = function (q) { state.quick = q || []; renderQuick(); save(); };

    var setHeader = function () {
      root.classList.toggle('is-live', !!state.live);
      var away = availability && availability.online === false;
      root.classList.toggle('is-away', !!away && !!state.live);
      if (state.live) {
        stateLabel.textContent = state.live.status === 'pending' ? 'En attente de votre réponse'
          : away ? 'Conseillers absents · réponse dès que possible' : 'En relation avec un conseiller';
      } else {
        stateLabel.textContent = advisors && availability && availability.online ? 'Conseiller disponible' : 'Assistant en ligne';
      }
    };

    /* Messages de l'assistant, avec indicateur de saisie */
    var queue = Promise.resolve();
    var botSays = function (lines, quick) {
      setQuick([]);
      (lines || []).forEach(function (html) {
        queue = queue.then(function () {
          return new Promise(function (resolve) {
            var typing = document.createElement('div');
            typing.className = 'msg msg-bot msg-typing';
            typing.innerHTML = '<i></i><i></i><i></i>';
            log.appendChild(typing); scroll();
            var wait = reduced ? 0 : Math.min(1400, 450 + html.replace(/<[^>]+>/g, '').length * 9);
            setTimeout(function () { typing.remove(); push({ from: 'bot', html: html }); resolve(); }, wait);
          });
        });
      });
      queue = queue.then(function () { setQuick(quick); scroll(); });
      return queue;
    };

    var userSays = function (text, to) {
      push({ from: 'user', html: esc(text) });
      setQuick([]);
      if (to) run(to); else handle(text);
    };

    /* ---------- Assistant ---------- */
    var run = function (id) {
      if (id === 'agent') return startFlow('agent');
      if (id === 'agent_close') return closeAgent();
      if (id === 'skip') return answer('');
      if (id === 'cancel') { state.flow = null; save(); return botSays(["Pas de souci, c'est annulé. Autre chose ?"], MENU); }
      if (id === 'lead_send') return sendLead();
      if (id === 'lead_restart') { var t = state.flow ? state.flow.type : 'demo'; state.profile = {}; return startFlow(t); }
      if (id.indexOf('lead_') === 0) return startFlow(id.slice(5));
      var it = INTENTS[id] || INTENTS.inconnu;
      var p = botSays(it.say, it.quick);
      if (it.next) p.then(function () { run(it.next); });
    };
    var detect = function (text) {
      var t = norm(text);
      for (var i = 0; i < ORDER.length; i++) {
        if (!INTENTS[ORDER[i]].match.test(t)) continue;
        return ORDER[i] === 'projet' && /approvia/.test(t) ? 'approvia' : ORDER[i];
      }
      return 'inconnu';
    };
    var handle = function (text) {
      if (state.live) return sendAgent(text);
      if (state.flow) return answer(text);
      run(detect(text));
    };

    /* ---------- Questions successives (démo, projet, conseiller) ---------- */
    var startFlow = function (type) {
      if (type === 'agent') {
        if (advisors === false) {
          return botSays(["La messagerie en direct n'est pas disponible pour le moment. Je peux transmettre votre demande à l'équipe, qui vous répondra par email."],
            [{ label: 'Transmettre ma demande', to: 'lead_projet' }, { label: 'Voir les sujets', to: 'menu' }]);
        }
        var intro = availability && availability.online === false
          ? [esc(availability.away_message || "Nos conseillers ne sont pas disponibles pour le moment : laissez votre message, nous vous répondrons dès que possible.")]
          : ['Je vous mets en relation avec un <b>conseiller NLapps</b>.'];
        state.flow = { type: 'agent', i: 0, data: {} }; save();
        return botSays(intro).then(nextStep);
      }
      state.flow = { type: type, i: 0, data: {} }; save();
      nextStep();
    };
    var nextStep = function () {
      var f = state.flow; if (!f) return;
      var keys = FLOWS[f.type];
      /* Saute les informations déjà connues */
      while (f.i < keys.length && STEPS[keys[f.i]].profile && state.profile[STEPS[keys[f.i]].profile]) {
        f.data[keys[f.i]] = state.profile[STEPS[keys[f.i]].profile]; f.i++;
      }
      save();
      if (f.i >= keys.length) return finishFlow();
      var step = STEPS[keys[f.i]];
      var q = [{ label: 'Annuler', to: 'cancel' }];
      if (step.optional) q.unshift({ label: 'Passer', to: 'skip' });
      botSays([step.askBy ? step.askBy[f.type] : step.ask], q).then(function () { input.focus({ preventScroll: true }); });
    };
    var answer = function (text) {
      var f = state.flow; if (!f) return;
      var key = FLOWS[f.type][f.i];
      if (!key) return; /* en attente de confirmation */
      var step = STEPS[key], v = String(text).trim();
      if (!v && !step.optional) return botSays(['Il me manque cette information pour continuer.'], [{ label: 'Annuler', to: 'cancel' }]);
      if (v && step.check && !step.check(v)) return botSays(['Cette adresse email ne semble pas valide. Pouvez-vous vérifier ?'], step.optional ? [{ label: 'Passer', to: 'skip' }, { label: 'Annuler', to: 'cancel' }] : [{ label: 'Annuler', to: 'cancel' }]);
      f.data[key] = v;
      if (step.profile && v) state.profile[step.profile] = v;
      f.i++; save();
      nextStep();
    };
    var finishFlow = function () {
      var f = state.flow, d = f.data;
      if (f.type === 'agent') { state.flow = null; save(); return openAgent(d.message); }
      botSays(['Merci ! Voici le récapitulatif :' +
        '<div class="msg-card"><b>' + esc(LEAD_LABEL[f.type]) + '</b>' +
        '<span>' + esc(d.nom) + ' · ' + esc(d.entreprise) + '</span>' +
        '<span>' + esc(d.email) + (d.telephone ? ' · ' + esc(d.telephone) : '') + '</span>' +
        '<span>« ' + esc(d.projet) + ' »</span></div>', "Je l'envoie à l'équipe ?"],
        [{ label: 'Envoyer ma demande', to: 'lead_send' }, { label: 'Recommencer', to: 'lead_restart' }, { label: 'Annuler', to: 'cancel' }]);
    };

    /* Demande de démo / projet : transmise au centre d'assistance si possible, sinon par le formulaire */
    var sendLead = function () {
      var f = state.flow; if (!f) return;
      var d = f.data, label = LEAD_LABEL[f.type];
      var text = label + '\n' + 'Entreprise : ' + d.entreprise + '\n' + 'Téléphone : ' + (d.telephone || '—') + '\n\n' + d.projet;
      state.flow = null; save();
      if (advisors !== false) {
        return openAgent(text, true).catch(function () { sendByForm(f.type, d, label); });
      }
      sendByForm(f.type, d, label);
    };
    var sendByForm = function (type, d, label) {
      var mailto = (contactForm && contactForm.getAttribute('data-mailto')) || 'contact@nlapps.fr';
      var endpoint = contactForm && contactForm.getAttribute('data-endpoint');
      var fd = new FormData();
      [['objet', label], ['nom', d.nom], ['prenom', ''], ['entreprise', d.entreprise], ['email', d.email], ['telephone', d.telephone || ''], ['projet', d.projet], ['source', 'Assistant NLapps']]
        .forEach(function (p) { fd.append(p[0], p[1]); });
      var done = function (html) { botSays([html], [{ label: 'Autre question', to: 'menu' }]); };
      if (endpoint) {
        return fetch(endpoint, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
          .then(function (r) { if (!r.ok) throw new Error(); done("C'est envoyé ! L'équipe NLapps revient vers vous très rapidement à l'adresse <b>" + esc(d.email) + '</b>.'); })
          .catch(function () { done("L'envoi n'a pas abouti. Vous pouvez nous écrire directement à <a href=\"mailto:" + esc(mailto) + '">' + esc(mailto) + '</a>.'); });
      }
      var body = ['Objet : ' + label, 'Nom : ' + d.nom, 'Entreprise : ' + d.entreprise, 'Email : ' + d.email, 'Téléphone : ' + (d.telephone || '—'), '', d.projet].join('\n');
      window.location.href = 'mailto:' + mailto + '?subject=' + encodeURIComponent(label + ' — ' + d.entreprise) + '&body=' + encodeURIComponent(body);
      done("Votre messagerie s'ouvre avec la demande pré-remplie : il ne reste plus qu'à l'envoyer. Merci !");
    };

    /* ---------- Conversation avec un conseiller ---------- */
    var transcript = function () {
      return state.messages.filter(function (m) { return m.from === 'user' || m.from === 'bot'; }).slice(-12)
        .map(function (m) { return { from: m.from, text: plain(m.html || '').slice(0, 1000) }; })
        .filter(function (m) { return m.text; });
    };
    var seen = function (id) { return state.live && state.live.seen.indexOf(id) >= 0; };
    var mark = function (id) { if (state.live && id && !seen(id)) { state.live.seen.push(id); if (state.live.seen.length > 400) state.live.seen.shift(); } };

    var showIncoming = function (list) {
      var fresh = false;
      (list || []).forEach(function (m) {
        if (!state.live) return;
        if (m.id > state.live.lastId) state.live.lastId = m.id;
        /* Les messages de l'utilisateur sont déjà affichés au moment de l'envoi */
        if (m.from === 'user' || seen(m.id)) { mark(m.id); return; }
        mark(m.id);
        push({ from: m.from, text: m.text, file: m.file, author: m.author });
        if (m.from === 'agent') fresh = true;
      });
      save();
      if (fresh && !isOpen()) {
        root.classList.add('has-dot');
        teaser.innerHTML = 'Nouvelle réponse d\'un <b>conseiller</b>';
        teaser.hidden = false;
      }
    };

    var openAgent = function (message, fromLead) {
      var typing = document.createElement('div');
      typing.className = 'msg msg-bot msg-typing'; typing.innerHTML = '<i></i><i></i><i></i>';
      log.appendChild(typing); scroll();
      return call({
        action: 'open', message: message, page: location.pathname + location.hash,
        user: { name: state.profile.name || '', email: state.profile.email || '', company: state.profile.company || '' },
        /* le premier message part à part : on ne le répète pas dans la transcription */
        transcript: transcript().filter(function (t, i, all) { return !(i === all.length - 1 && t.from === 'user' && t.text === message); })
      }).then(function (d) {
        typing.remove();
        if (d.availability) availability = d.availability;
        state.live = { lastId: 0, seen: [], status: d.status || 'open' };
        (d.messages || []).forEach(function (m) { if (m.from === 'user') { mark(m.id); if (m.id > state.live.lastId) state.live.lastId = m.id; } });
        save(); setHeader();
        var away = availability && availability.online === false;
        push({ from: 'system', text: fromLead
          ? 'Votre demande est transmise à l\'équipe NLapps.' + (away ? ' Nous vous répondrons dès que possible, ici et par email.' : ' Un conseiller peut vous répondre ici même.')
          : (away ? 'Message transmis. Un conseiller vous répondra dès que possible.' : 'Message transmis. Un conseiller vous répond dans un instant.') });
        showIncoming(d.messages);
        renderQuick();
        startPolling(true);
      }).catch(function (e) {
        typing.remove();
        if (e.status === 409) { state.live = { lastId: 0, seen: [], status: 'open' }; save(); setHeader(); renderQuick(); startPolling(true); return sendAgent(message, true); }
        if (e.status === 503 || e.status === 0 || e.status === 502) advisors = false;
        if (fromLead) throw e;
        botSays([esc(e.message) + ' Je peux transmettre votre demande à l\'équipe, qui vous répondra par email.'],
          [{ label: 'Transmettre ma demande', to: 'lead_projet' }, { label: 'Voir les sujets', to: 'menu' }]);
      });
    };

    var lost = function (msg) {
      stopPolling();
      state.live = null; save(); setHeader();
      push({ from: 'system', text: msg || 'Cette conversation est terminée.' });
      setQuick(MENU);
    };

    var sendAgent = function (text, silent) {
      return call({ action: 'send', text: text }).then(function (d) { mark(d.id); save(); poll(); })
        .catch(function (e) {
          if (e.data && e.data.reset) return lost(e.message);
          push({ from: 'system', text: e.message || "Votre message n'a pas pu être envoyé." });
        });
    };

    var closeAgent = function () {
      call({ action: 'close' }).catch(function () {}).then(function () {
        stopPolling();
        state.live = null; setHeader();
        push({ from: 'system', text: 'Vous avez terminé la conversation.' });
        askRating();
      });
    };

    /* Suivi : 4 s quand la fenêtre est ouverte, 45 s en arrière-plan ; arrêt à « closed » */
    var pollTimer = null, polling = false;
    var stopPolling = function () { clearTimeout(pollTimer); pollTimer = null; };
    var schedule = function () { stopPolling(); if (state.live) pollTimer = setTimeout(poll, isOpen() ? 4000 : 45000); };
    var startPolling = function (now) { if (now) poll(); else schedule(); };
    var poll = function () {
      if (!state.live || polling) return;
      polling = true; stopPolling();
      call({ action: 'poll', after: state.live.lastId }).then(function (d) {
        polling = false;
        if (!state.live) return;
        if (d.availability) availability = d.availability;
        showIncoming(d.messages);
        if (d.status === 'closed') {
          state.live = null; setHeader();
          push({ from: 'system', text: 'Le conseiller a clôturé la conversation.' + (state.profile.email ? ' La transcription vous est envoyée par email.' : '') });
          if (!d.rated) askRating(); else setQuick(MENU);
          return;
        }
        state.live.status = d.status; save(); setHeader();
        schedule();
      }).catch(function (e) {
        polling = false;
        if (e.data && e.data.reset) return lost(e.message);
        if (e.status === 503) return lost("L'assistance en direct est momentanément indisponible.");
        schedule(); /* coupure passagère : on réessaie au prochain cycle */
      });
    };

    /* Image jointe */
    form.querySelector('.chat-attach').addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files[0]; fileInput.value = '';
      if (!file || !state.live) return;
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 4 * 1024 * 1024) {
        return push({ from: 'system', text: 'Image refusée : JPEG, PNG ou WebP de 4 Mo maximum.' });
      }
      var reader = new FileReader();
      reader.onload = function () {
        var data = String(reader.result).split(',')[1];
        push({ from: 'user', local: URL.createObjectURL(file) });
        call({ action: 'attach', data: data, text: "Capture d'écran" }).then(function (d) { mark(d.id); save(); poll(); })
          .catch(function (e) { if (e.data && e.data.reset) return lost(e.message); push({ from: 'system', text: e.message }); });
      };
      reader.readAsDataURL(file);
    });

    /* Note de satisfaction */
    var askRating = function () {
      state.rating = true; state.messages.push({ from: 'rating' }); save();
      renderRating(); scroll(); setQuick([{ label: 'Nouvelle question', to: 'menu' }]);
    };
    var renderRating = function () {
      if (!state.rating) return;
      var el = document.createElement('div');
      el.className = 'msg msg-rating';
      el.innerHTML = '<b>Comment s\'est passé cet échange ?</b><div class="rating-stars" role="radiogroup" aria-label="Note de 1 à 5">' +
        [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" role="radio" aria-checked="false" aria-label="' + n + ' sur 5" data-n="' + n + '"><svg class="ico" aria-hidden="true"><use href="#i-star"/></svg></button>'; }).join('') +
        '</div><textarea rows="2" maxlength="1000" placeholder="Un commentaire ? (facultatif)" aria-label="Commentaire"></textarea><button type="button" class="btn btn-primary btn-sm" disabled>Envoyer ma note</button>';
      var note = 0, stars = el.querySelectorAll('.rating-stars button'), send = el.querySelector('.btn');
      stars.forEach(function (b) {
        b.addEventListener('click', function () {
          note = +b.getAttribute('data-n'); send.disabled = false;
          stars.forEach(function (s) { var on = +s.getAttribute('data-n') <= note; s.classList.toggle('on', on); s.setAttribute('aria-checked', String(+s.getAttribute('data-n') === note)); });
        });
      });
      send.addEventListener('click', function () {
        send.disabled = true;
        call({ action: 'rate', rating: note, comment: el.querySelector('textarea').value }).catch(function () {}).then(function () {
          state.rating = false;
          state.messages = state.messages.filter(function (m) { return m.from !== 'rating'; }); save();
          el.remove();
          botSays(['Merci pour votre retour ! Autre chose ?'], MENU);
        });
      });
      log.appendChild(el);
    };

    /* ---------- Saisie libre ---------- */
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var t = input.value.trim();
      var f = state.flow, step = f && STEPS[FLOWS[f.type][f.i]];
      if (!t && !(step && step.optional)) return;
      input.value = '';
      if (t) userSays(t); else userSays('Passer', 'skip');
    });

    /* ---------- Ouverture / fermeture ---------- */
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
      launcher.setAttribute('aria-label', open ? 'Fermer la conversation' : "Ouvrir la conversation avec l'assistant NLapps");
      if (open) { store.set(SEEN, '1'); start(); scroll(); if (focus) input.focus({ preventScroll: true }); }
      else if (focus) launcher.focus();
      if (state.live) { if (open) poll(); else schedule(); }
    };
    launcher.addEventListener('click', function () { setOpen(panel.hidden, true); });
    teaser.addEventListener('click', function () { setOpen(true, true); });
    root.querySelector('.chat-close').addEventListener('click', function () { setOpen(false, true); });
    root.querySelector('.chat-reset').addEventListener('click', function () {
      if (state.live) return;
      var profile = state.profile;
      state = blank(); state.profile = profile; save();
      log.innerHTML = ''; renderQuick(); queue = Promise.resolve(); run('accueil'); input.focus({ preventScroll: true });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) setOpen(false, true); });

    /* ---------- Disponibilité de l'équipe et reprise d'une conversation ---------- */
    setHeader();
    if (relay) {
      call({ action: 'status' }).then(function (d) {
        /* hébergement sans PHP : le relais ne répond pas en JSON → assistant seul */
        if (!d || !d.availability) throw new Error('relais indisponible');
        advisors = true; availability = d.availability || null;
        if (d.active && !state.live) state.live = { lastId: 0, seen: [], status: 'open' };
        if (!d.active && state.live) state.live = null;
        save(); setHeader(); renderQuick();
        if (state.live) startPolling(isOpen());
      }).catch(function () {
        advisors = false;
        if (state.live) { state.live = null; save(); }
        setHeader(); renderQuick();
      });
    }

    /* ---------- Ouverture automatique : une fois par session, sans interrompre une saisie ---------- */
    if (!store.get(SEEN)) {
      var delay = parseInt(root.getAttribute('data-delay'), 10) || 6000;
      setTimeout(function () {
        if (store.get(SEEN) || isOpen()) return;
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
