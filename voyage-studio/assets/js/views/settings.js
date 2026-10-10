// Réglages : agence, tarification, textes du devis, devises, assistant IA, mise à jour, sauvegardes, sécurité.

import { api } from '../api.js';
import { $, esc, state, toast, errorToast, confirmDialog, showFieldErrors, fileToBase64, money, date } from '../ui.js';

const SECTIONS = [
  ['Agence', [
    ['agence_nom', 'Nom commercial'], ['agence_adresse', 'Adresse', 'text'], ['agence_telephone', 'Téléphone'], ['agence_email', 'E-mail'],
    ['agence_site', 'Site web'], ['agence_siret', 'SIRET'], ['agence_immatriculation', 'N° immatriculation Atout France (IM…)'],
    ['agence_garantie', 'Garant financier'], ['agence_rcp', 'Assureur RC professionnelle'], ['agence_couleur', 'Couleur de marque', 'color'],
  ]],
  ['Tarification par défaut', [
    ['devise_base', 'Devise de vente'],
    ['tva_regime', 'Régime de TVA', 'select', { marge: 'Régime de la marge (TVA sur marge)', franchise: 'Franchise en base (micro-entreprise)' }],
    ['tva_taux', 'Taux de TVA (%)'],
    ['marge_mode', 'Mode de marge', 'select', { coef: 'Majoration % sur achat', marque: 'Taux de marque %', fixe_pers: 'Montant fixe / pers.', fixe_total: 'Montant fixe / dossier' }],
    ['marge_valeur', 'Valeur de marge'], ['frais_dossier', 'Frais de dossier (€ / dossier)'], ['frais_dossier_pers', 'Frais de dossier (€ / pers.)'],
    ['arrondi', 'Arrondi du prix / pers. (multiple de)'], ['securite_change', 'Sécurité de change sur achats en devises (%)'],
    ['acompte_pct', 'Acompte à la réservation (%)'], ['solde_jours', 'Solde à J- (jours avant départ)'],
  ]],
  ['Textes du devis', [
    ['devis_intro', 'Introduction', 'text'], ['devis_inclus', '« Le prix comprend » par défaut', 'text'],
    ['devis_non_inclus', '« Le prix ne comprend pas » par défaut', 'text'], ['devis_conditions', 'Conditions de vente', 'text'],
  ]],
];

function field([key, label, type, opts], v) {
  if (type === 'text') return `<label class="field span2"><span class="lbl">${esc(label)}</span><textarea name="${key}" rows="4">${esc(v)}</textarea></label>`;
  if (type === 'select') return `<label class="field"><span class="lbl">${esc(label)}</span><select name="${key}">${Object.entries(opts).map(([k, l]) => `<option value="${k}" ${v === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`;
  if (type === 'color') return `<label class="field"><span class="lbl">${esc(label)}</span><input type="color" name="${key}" value="${esc(v || '#0f766e')}"></label>`;
  return `<label class="field"><span class="lbl">${esc(label)}</span><input name="${key}" value="${esc(v)}"></label>`;
}

const fmtSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} Mo` : `${Math.ceil(b / 1024)} Ko`);

export async function render(el) {
  const [settings, upd, ai] = await Promise.all([api.get('settings'), api.get('update'), api.get('ai')]);
  state.settings = settings;
  const s = settings;

  el.innerHTML = `
    <div class="page-head"><h1>Réglages</h1><span class="muted">VoyageStudio v${esc(upd.version)} · PHP ${esc(upd.php)}</span></div>
    <nav class="tabs settings-nav">${['Agence & tarifs', 'Devises', 'Assistant IA', 'Mise à jour', 'Sauvegardes', 'Sécurité'].map((t, i) => `<button type="button" class="tab" data-scroll="set-${i}">${t}</button>`).join('')}</nav>

    <form class="card" id="set-0">
      ${SECTIONS.map(([title, fields]) => `<fieldset><legend>${esc(title)}</legend><div class="form-grid">${fields.map((f) => field(f, s[f[0]])).join('')}</div></fieldset>`).join('')}
      <fieldset><legend>Logo</legend>
        <div class="logo-row">${s.agence_logo ? `<img src="${esc(s.agence_logo)}" alt="Logo" class="logo-preview">` : '<span class="muted">Aucun logo</span>'}
          <input type="file" id="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"> ${s.agence_logo ? '<button type="button" class="btn small ghost" id="logo-del">Retirer</button>' : ''}</div>
        <small class="help">PNG/JPEG/SVG, 500 Ko max. Apparaît sur les devis.</small>
      </fieldset>
      <div class="form-actions"><button class="btn primary" type="submit">Enregistrer les réglages</button></div>
    </form>

    <section class="card" id="set-1">
      <div class="card-head"><h2>Devises (1 ${esc(s.devise_base)} = x)</h2><button class="btn" id="ecb">↻ Mettre à jour (BCE)</button></div>
      <table class="table"><thead><tr><th>Code</th><th>Taux</th><th>Mise à jour</th><th></th></tr></thead><tbody id="cur-body"></tbody></table>
      <form class="inline-form" id="cur-add"><input name="code" placeholder="Code (ex. XPF)" maxlength="3" required style="text-transform:uppercase"><input name="taux" placeholder="Taux" inputmode="decimal" required><button class="btn small">Ajouter</button></form>
      <p class="muted small">Taux de référence de la Banque centrale européenne. Vous pouvez saisir votre propre taux (taux fournisseur ou bancaire) : il sera écrasé à la prochaine mise à jour BCE.</p>
    </section>

    <section class="card" id="set-2">
      <h2>✨ Assistant IA</h2>
      <p>Statut : ${ai.enabled ? `<span class="badge green">Actif</span> · modèle <code>${esc(ai.model)}</code>` : '<span class="badge gray">Inactif</span>'}</p>
      <ul class="checks">
        <li class="${ai.sdk ? 'ok' : 'ko'}">${ai.sdk ? '✔' : '✖'} Bibliothèque Anthropic (dossier <code>vendor/</code>)</li>
        <li class="${ai.key ? 'ok' : 'ko'}">${ai.key ? '✔' : '✖'} Clé API configurée</li>
      </ul>
      ${ai.enabled ? '' : `<p>Pour l'activer, éditez <code>config.php</code> via votre FTP et renseignez votre clé :</p>
        <pre>'ai' => [\n    'api_key' => 'sk-ant-…',\n    'model' => 'claude-opus-5-5',\n],</pre>
        <p class="muted small">Clé à créer sur console.anthropic.com (facturation à l'usage : quelques centimes par import). La clé reste sur votre serveur et n'est jamais envoyée au navigateur.</p>`}
      <p class="muted small">Fonctions : import de prestations depuis une capture d'écran, un PDF ou un e-mail ; relecture d'un voyage avec suggestions d'oublis.</p>
    </section>

    <section class="card" id="set-3">
      <h2>⬆ Mise à jour de l'application</h2>
      <p>Version installée : <b>v${esc(upd.version)}</b>. Déposez le ZIP d'une nouvelle version : l'application vérifie le paquet,
        <b>sauvegarde automatiquement</b> les fichiers et la base, puis installe la mise à jour sans toucher à <code>config.php</code> ni à vos données.</p>
      ${!upd.zip ? '<p class="alert error">L\'extension PHP <code>zip</code> est absente : mise à jour possible uniquement par FTP.</p>' : ''}
      ${!upd.writable ? '<p class="alert error">Les fichiers de l\'application ne sont pas modifiables par PHP : utilisez le FTP, ou demandez à votre hébergeur.</p>' : ''}
      <form id="upd-form" class="upd-form">
        <input type="file" name="package" accept=".zip,application/zip" required>
        <label class="field check"><input type="checkbox" name="allow_downgrade" value="1"> <span>Autoriser le retour à une version antérieure</span></label>
        <button class="btn primary" ${upd.zip && upd.writable ? '' : 'disabled'}>Vérifier le paquet</button>
      </form>
      <p class="muted small">Taille maximale acceptée par votre hébergement : ${upd.max_upload > 1e15 ? 'illimitée' : fmtSize(upd.max_upload)}. Si le ZIP complet est trop lourd, utilisez le paquet « sans vendor » (le dossier vendor/ existant est conservé).</p>
      <div id="upd-result"></div>
    </section>

    <section class="card" id="set-4">
      <h2>💾 Sauvegardes</h2>
      <div class="btn-row">
        <button class="btn" id="bk-export">⬇ Exporter toutes les données (JSON)</button>
        <label class="btn">⬆ Restaurer des données…<input type="file" id="bk-import" accept=".json,application/json" hidden></label>
        <button class="btn" id="bk-full">📦 Sauvegarde complète maintenant (fichiers + base)</button>
      </div>
      <p class="muted small">Conseil : exportez vos données chaque semaine et conservez-les hors du serveur. La restauration remplace toutes les données actuelles.</p>
      <h3>Sauvegardes sur le serveur</h3>
      ${upd.backups.length ? `<table class="table"><tbody>${upd.backups.map((b) => `<tr><td>${esc(b.file)}</td><td>${esc(b.date)}</td><td>${fmtSize(b.size)}</td><td><button class="btn small" data-dl="${esc(b.file)}">Télécharger</button></td></tr>`).join('')}</tbody></table>` : '<p class="muted">Aucune (créées automatiquement avant chaque mise à jour).</p>'}
      <p class="muted small">Pour revenir en arrière après une mise à jour : téléchargez le ZIP « app-… » et déposez-le dans « Mise à jour » en cochant « Autoriser le retour à une version antérieure ».</p>
    </section>

    <form class="card" id="set-5">
      <h2>🔒 Mot de passe</h2>
      <div class="form-grid">
        <label class="field"><span class="lbl">Mot de passe actuel</span><input type="password" name="current" required autocomplete="current-password"></label>
        <label class="field"><span class="lbl">Nouveau (10 caractères min.)</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      </div>
      <div class="form-actions"><button class="btn">Changer le mot de passe</button></div>
    </form>`;

  const drawCurrencies = () => {
    $('#cur-body', el).innerHTML = state.currencies.map((c) => `<tr data-cur="${c.id}"><td><b>${esc(c.code)}</b></td>
      <td><input class="w8" value="${esc(String(c.taux).replace('.', ','))}" data-rate inputmode="decimal" ${c.code === 'EUR' ? 'disabled' : ''}></td><td class="muted small">${esc(c.maj || '')}</td>
      <td>${c.code === 'EUR' ? '' : '<button class="icon-btn danger" data-del-cur title="Supprimer">✕</button>'}</td></tr>`).join('');
  };
  drawCurrencies();

  // Réglages
  const form = $('#set-0', el);
  form.onsubmit = async (e) => {
    e.preventDefault();
    const body = {};
    for (const elx of form.elements) if (elx.name) body[elx.name] = elx.value;
    try {
      state.settings = await api.put('settings', body);
      toast('Réglages enregistrés');
      document.documentElement.style.setProperty('--brand', state.settings.agence_couleur);
    } catch (err) {
      showFieldErrors(form, err);
    }
  };
  $('#logo', el).onchange = async (e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (f.size > 500000) return toast('Logo trop lourd (500 Ko max)', 'error');
    try {
      state.settings = await api.put('settings', { agence_logo: `data:${f.type};base64,${await fileToBase64(f)}` });
      toast('Logo enregistré');
      render(el);
    } catch (err) {
      errorToast(err);
    }
  };
  const del = $('#logo-del', el);
  if (del) del.onclick = async () => { state.settings = await api.put('settings', { agence_logo: '' }); render(el); };

  // Devises
  $('#ecb', el).onclick = async (e) => {
    e.target.disabled = true;
    try {
      const r = await api.post('rates/refresh');
      state.currencies = r.currencies;
      drawCurrencies();
      toast(`${r.updated} taux mis à jour (BCE du ${date(r.date)})`);
    } catch (err) {
      errorToast(err);
    } finally {
      e.target.disabled = false;
    }
  };
  $('#cur-add', el).onsubmit = async (e) => {
    e.preventDefault();
    try {
      const c = await api.post('currencies', { code: e.target.code.value, taux: e.target.taux.value, maj: 'manuel ' + new Date().toLocaleDateString('fr-FR') });
      state.currencies.push(c);
      state.currencies.sort((a, b) => a.code.localeCompare(b.code));
      e.target.reset();
      drawCurrencies();
    } catch (err) {
      errorToast(err);
    }
  };
  $('#cur-body', el).onchange = async (e) => {
    const tr = e.target.closest('[data-cur]');
    if (!tr || !e.target.matches('[data-rate]')) return;
    try {
      const c = await api.put(`currencies/${tr.dataset.cur}`, { taux: e.target.value, maj: 'manuel ' + new Date().toLocaleDateString('fr-FR') });
      Object.assign(state.currencies.find((x) => x.id === c.id), c);
      toast(`Taux ${c.code} enregistré`);
      drawCurrencies();
    } catch (err) {
      errorToast(err);
    }
  };
  $('#cur-body', el).onclick = async (e) => {
    const tr = e.target.closest('[data-cur]');
    if (!tr || !e.target.matches('[data-del-cur]')) return;
    await api.del(`currencies/${tr.dataset.cur}`);
    state.currencies = state.currencies.filter((c) => c.id !== +tr.dataset.cur);
    drawCurrencies();
  };

  // Mise à jour
  const updForm = $('#upd-form', el);
  const out = $('#upd-result', el);
  updForm.onsubmit = async (e) => {
    e.preventDefault();
    const file = updForm.package.files[0];
    if (!file) return;
    if (file.size > upd.max_upload) return toast(`Fichier trop volumineux pour l'hébergement (${fmtSize(file.size)} > ${fmtSize(upd.max_upload)})`, 'error', 8000);
    const fd = new FormData(updForm);
    const btn = $('button', updForm);
    btn.disabled = true;
    try {
      const info = await api.upload('update/inspect', fd);
      const older = info.version.localeCompare(info.current, undefined, { numeric: true }) < 0;
      const same = info.version === info.current;
      out.innerHTML = `<div class="alert ${older ? 'error' : 'info'}">Paquet <b>v${esc(info.version)}</b> (installée : v${esc(info.current)}) — ${info.files} fichiers${info.vendor ? '' : ', sans dossier vendor/'}.
        ${older ? '<br>⚠️ Version plus ancienne que celle installée.' : same ? '<br>Même version : réinstallation.' : ''}</div>
        <button class="btn primary" id="upd-go">Installer v${esc(info.version)}</button>`;
      $('#upd-go', el).onclick = async (ev) => {
        if (!(await confirmDialog(`Installer la version ${info.version} ? Une sauvegarde complète sera faite avant.`, { label: 'Installer' }))) return;
        ev.target.disabled = true;
        ev.target.textContent = 'Installation…';
        try {
          const r = await api.upload('update/install', new FormData(updForm));
          out.innerHTML = `<div class="alert ok">✔ Mise à jour v${esc(r.from)} → v${esc(r.to)} installée (${r.written} fichiers, ${r.removed} obsolètes supprimés).<br>Sauvegarde : ${esc(r.backup.files)} + ${esc(r.backup.database)}</div>
            <button class="btn primary" id="reload">Recharger l'application</button>`;
          $('#reload', el).onclick = () => location.reload();
        } catch (err) {
          out.innerHTML = `<div class="alert error">${esc(err.message)}</div>`;
        }
      };
    } catch (err) {
      out.innerHTML = `<div class="alert error">${esc(err.message)}</div>`;
    } finally {
      btn.disabled = false;
    }
  };

  // Sauvegardes
  $('#bk-export', el).onclick = () => api.download('backup');
  $('#bk-full', el).onclick = async (e) => {
    e.target.disabled = true;
    try {
      const r = await api.post('update/backup');
      toast(`Sauvegarde créée : ${r.files}`);
      render(el);
    } catch (err) {
      errorToast(err);
      e.target.disabled = false;
    }
  };
  el.onclick = (e) => {
    const sc = e.target.closest('[data-scroll]');
    if (sc) return document.getElementById(sc.dataset.scroll)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const b = e.target.closest('[data-dl]');
    if (b) api.download('update/download', { file: b.dataset.dl });
  };
  $('#bk-import', el).onchange = async (e) => {
    const f = e.target.files[0];
    if (!f) return;
    let json;
    try {
      json = JSON.parse(await f.text());
    } catch {
      return toast('Fichier JSON invalide', 'error');
    }
    const counts = Object.entries(json.tables || {}).map(([k, v]) => `${v.length} ${k}`).join(', ');
    if (!(await confirmDialog(`Remplacer TOUTES les données actuelles par cette sauvegarde (${json.date || '?'} : ${counts}) ?`, { label: 'Restaurer' }))) return;
    try {
      await api.post('backup', json);
      toast('Données restaurées');
      setTimeout(() => location.reload(), 800);
    } catch (err) {
      errorToast(err);
    }
  };

  // Mot de passe
  const pw = $('#set-5', el);
  pw.onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api.post('auth/password', { current: pw.current.value, password: pw.password.value });
      pw.reset();
      toast('Mot de passe modifié');
    } catch (err) {
      showFieldErrors(pw, err);
    }
  };
  void money;
}
