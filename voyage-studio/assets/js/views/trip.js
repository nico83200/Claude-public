// Éditeur de dossier : variantes, prestations, rentabilité en direct, suggestions anti-oubli, voyageurs, échéancier.

import { api } from '../api.js';
import { navigate, refreshRefs, hashParams } from '../app.js';
import {
  $, $$, esc, nl2br, money, pct, date, badge, state, modal, confirmDialog, entityForm, enhanceForm, readForm,
  toast, errorToast, ITEM_ICONS, enumLabel, refLabel, emptyState,
} from '../ui.js';
import { computeOption, paymentSchedule } from '../pricing.js';
import { fmtDuration, itemDuration, todayIso, daysBetween, sortChrono } from '../time.js';
import { airport } from '../airports.js';
import { runChecks, generateDays } from '../checks.js';
import { quickCreate } from './trips.js';
import { openAiImport, aiSummary } from './ai-import.js';

let data = null;
let ui = { optionId: null, tab: 'build', aiSuggestions: null };

export async function render(el, [id]) {
  const p = hashParams();
  ui = { optionId: p.get('opt') ? +p.get('opt') : null, tab: p.get('tab') || 'build', aiSuggestions: null };
  await load(id);
  draw(el);
  el.onclick = (e) => onClick(e, el);
  el.onchange = (e) => onChange(e, el);
}

async function load(id) {
  data = await api.get(`trips/${id ?? data.trip.id}/full`);
  if (!data.options.some((o) => o.id === ui.optionId)) {
    ui.optionId = data.trip.selected_option_id && data.options.some((o) => o.id === data.trip.selected_option_id)
      ? data.trip.selected_option_id : data.options[0]?.id;
  }
}

async function reload(el) {
  await load();
  draw(el);
}

const ctx = () => ({
  trip: data.trip, settings: data.settings, currencies: data.currencies, horsUE: !!+data.destination?.hors_ue,
});
const currentOption = () => data.options.find((o) => o.id === ui.optionId);

function draw(el) {
  const t = data.trip;
  const opt = currentOption();
  const computed = opt ? computeOption(opt, ctx()) : null;
  syncTotals();
  const pax = `${t.nb_adultes || 0} adulte${t.nb_adultes > 1 ? 's' : ''}${+t.nb_enfants ? `, ${t.nb_enfants} enfant${t.nb_enfants > 1 ? 's' : ''}` : ''}${+t.nb_bebes ? `, ${t.nb_bebes} bébé${t.nb_bebes > 1 ? 's' : ''}` : ''}`;
  const nights = daysBetween(t.date_depart, t.date_retour);
  const statuses = state.schema.entities.trips.fields.statut.options;

  el.innerHTML = `
    <div class="trip-head card">
      <div class="trip-title">
        <span class="mono muted">${esc(t.reference)}</span>
        <h1>${esc(t.titre)}</h1>
        <div class="meta">
          ${data.client ? `<a href="#/clients/${data.client.id}">👤 ${esc(data.client.prenom || '')} ${esc(data.client.nom)}</a>` : '<span class="muted">Pas de client</span>'}
          ${data.destination ? `<a href="#/destinations/${data.destination.id}">🌍 ${esc(data.destination.nom)}</a>` : ''}
          <span>📅 ${t.date_depart ? `${date(t.date_depart)} → ${date(t.date_retour)}${nights ? ` (${nights + 1} j / ${nights} n)` : ''}` : 'Dates à définir'}</span>
          <span>👥 ${pax}</span>
          ${t.budget ? `<span>💶 Budget ${money(t.budget, null, false)}</span>` : ''}
        </div>
      </div>
      <div class="trip-actions">
        <select id="trip-status" aria-label="Statut">${Object.entries(statuses).map(([k, l]) => `<option value="${k}" ${t.statut === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>
        <button class="btn" data-act="edit-trip">✎ Modifier</button>
        <button class="btn" data-act="dup-trip" title="Réutiliser ce dossier comme modèle">⧉ Dupliquer</button>
        <a class="btn" href="#/trips/${t.id}/compare">⇄ Comparer</a>
        <button class="btn danger ghost" data-act="del-trip" title="Supprimer">🗑</button>
      </div>
    </div>
    ${t.demande ? `<details class="card demand"><summary>Demande du client</summary><p>${nl2br(t.demande)}</p></details>` : ''}

    <nav class="tabs" role="tablist">
      ${[['build', 'Construction du voyage'], ['travelers', `Voyageurs (${data.travelers.length})`], ['payments', `Échéancier (${data.payments.length})`]]
        .map(([k, l]) => `<button role="tab" class="tab ${ui.tab === k ? 'active' : ''}" data-tab="${k}">${l}</button>`).join('')}
    </nav>
    <div id="tab-body">${ui.tab === 'travelers' ? travelersTab() : ui.tab === 'payments' ? paymentsTab(computed) : buildTab(opt, computed)}</div>`;
}

/* ================================================================== Construction */

function buildTab(opt, c) {
  const optTabs = data.options.map((o) => {
    const oc = computeOption(o, ctx());
    return `<button class="opt-tab ${o.id === ui.optionId ? 'active' : ''}" data-opt="${o.id}">
      ${o.id === data.trip.selected_option_id ? '<span title="Variante retenue">★</span>' : ''} ${esc(o.nom)}
      <small>${money(oc.totals.parPers, null, false)} /pers</small></button>`;
  }).join('');

  if (!opt) return emptyState('🧩', 'Aucune variante.');

  return `
    <div class="opt-tabs">${optTabs}<button class="opt-tab add" data-act="add-option">+ Variante</button></div>
    <div class="opt-toolbar">
      <div>
        ${opt.id === data.trip.selected_option_id ? '<span class="badge green">★ Variante retenue</span>' : '<button class="btn small" data-act="select-option">★ Retenir cette variante</button>'}
        <button class="btn small" data-act="edit-option">✎ Tarification & textes</button>
        <button class="btn small" data-act="dup-option">⧉ Dupliquer</button>
        <button class="btn small ghost danger" data-act="del-option">Supprimer</button>
      </div>
      <a class="btn primary" href="#/trips/${data.trip.id}/quote/${opt.id}">📄 Devis client</a>
    </div>
    ${opt.resume ? `<p class="muted">${nl2br(opt.resume)}</p>` : ''}
    <div class="build-grid">
      <div class="build-main">
        <div class="card">
          <div class="card-head"><h2>Prestations</h2>
            <div class="add-bar">
              ${state.ai.enabled ? '<button class="btn ai" data-act="ai-import" title="Capture d\'écran, PDF ou e-mail de confirmation">📷 Importer (IA)</button>' : '<button class="btn ai" data-act="ai-off" title="Assistant IA non configuré">📷 Importer (IA)</button>'}
              ${['vol', 'hebergement', 'croisiere', 'transfert', 'train', 'location', 'activite', 'assurance'].map((t) => `<button class="btn small" data-add-type="${t}" title="Ajouter : ${esc(state.schema.itemTypes[t])}">${ITEM_ICONS[t]} ${esc(state.schema.itemTypes[t].split(' ')[0])}</button>`).join('')}
              <button class="btn small" data-add-type="autre">+ Autre</button>
            </div>
          </div>
          ${itemsTimeline(c)}
        </div>
        <div class="card">
          <div class="card-head"><h2>Programme jour par jour</h2>
            <div><button class="btn small" data-act="gen-days">⚡ Générer depuis les prestations</button> <button class="btn small" data-act="add-day">+ Jour</button></div></div>
          ${daysList(opt)}
        </div>
      </div>
      <aside class="build-side">
        ${pricingPanel(c)}
        ${statsPanel(c)}
        ${suggestionsPanel(opt, c)}
      </aside>
    </div>`;
}

function itemDetails(i, line) {
  const parts = [];
  const dur = itemDuration(i);
  if (['vol', 'train', 'transfert'].includes(i.type) || (i.lieu_depart && i.lieu_arrivee)) {
    const from = i.lieu_depart ? `${esc(i.lieu_depart)}${i.heure_debut ? ' ' + i.heure_debut : ''}` : '';
    const to = i.lieu_arrivee ? `${esc(i.lieu_arrivee)}${i.heure_fin ? ' ' + i.heure_fin : ''}${i.date_fin && i.date_debut && i.date_fin > i.date_debut ? ` (+${daysBetween(i.date_debut, i.date_fin)})` : ''}` : '';
    if (from || to) parts.push(`${from} → ${to}`);
  } else if (i.heure_debut) parts.push(i.heure_debut);
  if (i.compagnie || i.numero) parts.push(esc([i.compagnie, i.numero].filter(Boolean).join(' ')));
  if (dur) parts.push(`⏱ ${fmtDuration(dur)}`);
  if (i.type === 'vol') parts.push(+i.escales ? `${i.escales} escale${i.escales > 1 ? 's' : ''}` : 'direct');
  if (i.classe) parts.push(esc(i.classe));
  if (i.bagages) parts.push(`🧳 ${esc(i.bagages)}`);
  if (i.navire) parts.push(esc(i.navire));
  if (i.categorie) parts.push(esc(i.categorie));
  if (i.chambre) parts.push(esc(i.chambre));
  if (i.pension) parts.push(esc(enumLabel('items', 'pension', i.pension)));
  if (i.mode_transfert) parts.push(esc(enumLabel('items', 'mode_transfert', i.mode_transfert)));
  if (['hebergement', 'croisiere', 'location'].includes(i.type) && i.date_fin) parts.push(`${daysBetween(i.date_debut, i.date_fin)} nuit(s) · jusqu'au ${date(i.date_fin)}`);
  if (i.supplier_id) parts.push(`🏢 ${esc(refLabel('suppliers', i.supplier_id))}`);
  if (i.ref_reservation) parts.push(`Réf. ${esc(i.ref_reservation)}`);
  if (line) {
    const unit = enumLabel('items', 'unite', i.unite || 'forfait');
    parts.push(`<span class="muted">${money(i.prix_unitaire, i.devise)} × ${+line.qty.toFixed(2)}${line.mult > 1 ? ` × ${line.mult}` : ''} (${esc(unit.toLowerCase())})${+i.taxes ? ` + taxes ${money(i.taxes, i.devise)}` : ''}${i.mode_tarif === 'commission' ? ` · commission ${i.commission_pct || 0} %` : ''}</span>`);
  }
  return parts.join(' · ');
}

function itemsTimeline(c) {
  if (!c.lines.length) return emptyState('🧩', 'Ajoutez les prestations du voyage (vols, hôtels, croisière, transferts…) ou importez une capture d\'écran.');
  const groups = new Map();
  const order = sortChrono(c.lines.map((l) => l.item));
  for (const l of order.map((i) => c.lines.find((x) => x.item === i))) {
    const k = l.item.date_debut || '';
    if (!groups.has(k)) groups.set(k, []);
    groups.get(k).push(l);
  }
  const cancelled = (currentOption().items || []).filter((i) => i.statut === 'annule');
  const start = data.trip.date_depart;
  return `<div class="timeline">${[...groups.entries()].sort(([a], [b]) => (a || '9999').localeCompare(b || '9999')).map(([d, lines]) => `
    <div class="tl-day"><div class="tl-date">${d ? `${date(d, true)}${start ? ` <span class="muted">· J${daysBetween(start, d) + 1}</span>` : ''}` : 'Sans date'}</div>
    ${lines.map((l) => {
      const i = l.item;
      return `<div class="tl-item ${l.optional ? 'optional' : ''}" data-item="${i.id}">
        <div class="tl-ico" title="${esc(state.schema.itemTypes[i.type])}">${ITEM_ICONS[i.type] || '•'}</div>
        <div class="tl-body">
          <div class="tl-title"><b>${esc(i.libelle)}</b> ${l.optional ? '<span class="badge violet">En option</span>' : ''} ${badge('items', 'statut', i.statut)}
            ${i.statut === 'option' && i.date_limite_option ? `<span class="badge amber">jusqu'au ${date(i.date_limite_option)}</span>` : ''}</div>
          <div class="tl-details">${itemDetails(i, l)}</div>
        </div>
        <div class="tl-price">
          <div title="Prix de vente">${money(l.sell)}</div>
          <small class="muted" title="Achat / marge">achat ${money(l.cost)} · <span class="${l.margin < 0 ? 'neg' : 'pos'}">${money(l.margin)}</span></small>
        </div>
        <div class="tl-actions">
          <button class="icon-btn" data-act="edit-item" title="Modifier">✎</button>
          <button class="icon-btn" data-act="dup-item" title="Dupliquer">⧉</button>
          <button class="icon-btn danger" data-act="del-item" title="Supprimer">🗑</button>
        </div>
      </div>`;
    }).join('')}</div>`).join('')}
    ${cancelled.length ? `<p class="muted small">${cancelled.length} prestation(s) annulée(s) non comptée(s) : ${cancelled.map((i) => `<a href="#" data-item-link="${i.id}">${esc(i.libelle)}</a>`).join(', ')}</p>` : ''}
  </div>`;
}

function pricingPanel(c) {
  const t = c.totals;
  const s = data.settings;
  const tvaLabel = s.tva_regime === 'franchise' ? 'TVA (franchise en base)' : data.destination && +data.destination.hors_ue ? 'TVA sur marge (hors UE : exonérée)' : `TVA sur marge (${s.tva_taux} %)`;
  const types = Object.entries(c.byType).sort((a, b) => b[1].vente - a[1].vente);
  return `<div class="card pricing">
    <h2>Rentabilité</h2>
    <div class="big-price"><span>Prix client</span><strong>${money(t.vente)}</strong><small>${money(t.parPers)} / pers. (${t.pax} payant${t.pax > 1 ? 's' : ''})${t.parPersNuit ? ` · ${money(t.parPersNuit)} /pers./nuit` : ''}</small></div>
    <dl class="kv">
      <dt>Total achats</dt><dd>${money(t.achat)}</dd>
      ${t.commissions ? `<dt>dont commissions perçues</dt><dd>${money(t.commissions)}</dd>` : ''}
      ${t.taxes ? `<dt>Taxes refacturées</dt><dd>${money(t.taxes)}</dd>` : ''}
      ${t.fees ? `<dt>Frais de dossier</dt><dd>${money(t.fees)}</dd>` : ''}
      ${t.arrondi ? `<dt>Arrondi commercial</dt><dd>${money(t.arrondi)}</dd>` : ''}
      <dt><b>Marge brute</b></dt><dd><b class="${t.marge < 0 ? 'neg' : 'pos'}">${money(t.marge)}</b></dd>
      <dt>Taux de marque · coef.</dt><dd>${pct(t.tauxMarque)} · ${t.coef ? t.coef.toFixed(3).replace('.', ',') : '—'}</dd>
      <dt>${tvaLabel}</dt><dd>${money(t.tva)}</dd>
      <dt><b>Marge nette</b></dt><dd><b>${money(t.margeNette)}</b></dd>
      ${t.optionnel ? `<dt>Prestations en option</dt><dd>${money(t.optionnel)}</dd>` : ''}
      ${t.budgetEcart !== null ? `<dt>Écart / budget client</dt><dd class="${t.budgetEcart > 0 ? 'neg' : 'pos'}">${t.budgetEcart > 0 ? '+' : ''}${money(t.budgetEcart)}</dd>` : ''}
    </dl>
    ${c.missingRates.length ? `<p class="alert error small">Taux manquant : ${c.missingRates.join(', ')}</p>` : ''}
    ${types.length ? `<div class="mix">${types.map(([k, v]) => `<div class="mix-row"><span>${ITEM_ICONS[k]} ${esc(state.schema.itemTypes[k])}</span><div class="mix-bar"><i style="width:${t.vente ? (v.vente / t.vente) * 100 : 0}%"></i></div><span>${money(v.vente, null, false)}</span></div>`).join('')}</div>` : ''}
  </div>`;
}

function statsPanel(c) {
  const s = c.stats;
  return `<div class="card">
    <h2>Le voyage en chiffres</h2>
    <dl class="kv">
      <dt>Durée</dt><dd>${s.days ? `${s.days} jours / ${s.nights} nuits` : '—'}</dd>
      <dt>Vols</dt><dd>${s.flights ? `${s.flights} · ${fmtDuration(s.flightMin)} · ${s.stops} escale${s.stops > 1 ? 's' : ''}` : '—'}</dd>
      <dt>Transferts</dt><dd>${s.transfers ? `${s.transfers} · ${fmtDuration(s.transferMin)}` : '—'}</dd>
      <dt>Temps de transport</dt><dd>${s.travelMin ? fmtDuration(s.travelMin) : '—'}</dd>
      <dt>Nuits hébergées</dt><dd>${s.stayNights || '—'}</dd>
      ${s.boards.length ? `<dt>Formules</dt><dd>${s.boards.map((b) => esc(enumLabel('items', 'pension', b))).join(', ')}</dd>` : ''}
      <dt>Restant à réserver</dt><dd>${s.toBook ? `<span class="badge amber">${s.toBook}</span>` : '<span class="badge green">0</span>'}</dd>
    </dl>
  </div>`;
}

function suggestionsPanel(opt, c) {
  const list = runChecks({ trip: data.trip, option: opt, destination: data.destination, travelers: data.travelers, client: data.client, computed: c });
  const icon = { important: '⚠️', conseil: '💡', info: 'ℹ️' };
  const row = (s, idx, src) => `<li class="sg ${s.level}"><span class="sg-ico">${icon[s.level] || '💡'}</span><div><b>${esc(s.titre || s.title)}</b>${(s.detail) ? `<p>${esc(s.detail)}</p>` : ''}</div>
    ${s.add || s.type_prestation ? `<button class="btn small" data-suggest="${src}:${idx}">+ Ajouter</button>` : ''}</li>`;
  ui.lastChecks = list;
  return `<div class="card suggestions">
    <div class="card-head"><h2>Suggestions <span class="badge ${list.some((s) => s.level === 'important') ? 'red' : 'green'}">${list.length}</span></h2>
      ${state.ai.enabled ? '<button class="btn small ai" data-act="ai-suggest" title="Relecture complète par l\'IA">✨ Analyse IA</button>' : ''}</div>
    ${list.length ? `<ul class="sg-list">${list.map((s, i) => row(s, i, 'rule')).join('')}</ul>` : '<p class="muted">✅ Aucun oubli détecté.</p>'}
    ${ui.aiSuggestions ? `<h3>Analyse IA</h3><ul class="sg-list">${ui.aiSuggestions.map((s, i) => row({ ...s, level: s.niveau }, i, 'ai')).join('') || '<li class="muted">Rien à signaler.</li>'}</ul>` : ''}
  </div>`;
}

function daysList(opt) {
  const days = opt.days || [];
  if (!days.length) return '<p class="muted">Aucun programme. Générez-le automatiquement depuis les prestations puis personnalisez-le.</p>';
  return `<ol class="days">${days.map((d) => `<li data-day="${d.id}">
    <div class="day-n">J${d.jour}</div>
    <div class="day-body"><b>${esc(d.titre)}</b>${d.lieu ? ` <span class="muted">· nuit : ${esc(d.lieu)}</span>` : ''}${d.repas ? ` <span class="muted">· repas : ${esc(d.repas)}</span>` : ''}
      ${d.description ? `<p>${nl2br(d.description)}</p>` : ''}</div>
    <div class="tl-actions"><button class="icon-btn" data-act="edit-day">✎</button><button class="icon-btn danger" data-act="del-day">🗑</button></div></li>`).join('')}</ol>`;
}

/* ================================================================== Voyageurs */

function travelersTab() {
  const limit = data.trip.date_retour ? new Date(Date.parse(data.trip.date_retour) + 182 * 86400000).toISOString().slice(0, 10) : null;
  const rows = data.travelers.map((tr) => {
    const c = tr.client || {};
    const warn = limit && c.passeport_expiration && c.passeport_expiration < limit;
    return `<tr data-traveler="${tr.id}">
      <td><a href="#/clients/${c.id}">${esc(c.civilite || '')} ${esc(c.prenom || '')} <b>${esc(c.nom || '?')}</b></a></td>
      <td>${date(c.date_naissance)}</td>
      <td>${esc(c.passeport_numero || '')}</td>
      <td class="${warn ? 'neg' : ''}">${date(c.passeport_expiration)}${warn ? ' ⚠️' : ''}</td>
      <td>${esc(c.regime || '')}</td>
      <td class="tl-actions"><button class="icon-btn danger" data-act="del-traveler" title="Retirer">✕</button></td></tr>`;
  }).join('');
  const nb = (+data.trip.nb_adultes || 0) + (+data.trip.nb_enfants || 0) + (+data.trip.nb_bebes || 0);
  return `<div class="card">
    <div class="card-head"><h2>Voyageurs ${data.travelers.length}/${nb}</h2>
      <div><select id="add-traveler"><option value="">Ajouter un client existant…</option>${(state.refs.clients || []).filter((c) => !data.travelers.some((t) => t.client_id === c.id)).map((c) => `<option value="${c.id}">${esc(c.nom)} ${esc(c.prenom || '')}</option>`).join('')}</select>
      <button class="btn small" data-act="new-traveler">+ Nouveau voyageur</button></div></div>
    ${rows ? `<table class="table"><thead><tr><th>Nom</th><th>Naissance</th><th>Passeport</th><th>Expiration</th><th>Régime / mobilité</th><th></th></tr></thead><tbody>${rows}</tbody></table>` : '<p class="muted">Indiquez les voyageurs (noms exacts du passeport) pour les réservations et le contrôle des documents.</p>'}
  </div>`;
}

/* ================================================================== Échéancier */

function paymentsTab(computed) {
  const p = data.payments;
  const sum = (sens, paid) => p.filter((x) => x.sens === sens && (paid === undefined || !!+x.paye === paid)).reduce((s, x) => s + x.montant, 0);
  const sel = data.options.find((o) => o.id === data.trip.selected_option_id);
  const selTotal = sel ? computeOption(sel, ctx()).totals : null;
  void computed;
  return `<div class="grid-2">
    <div class="card"><h2>Client</h2><dl class="kv">
      <dt>Prix de vente (variante retenue)</dt><dd>${selTotal ? money(selTotal.vente) : '—'}</dd>
      <dt>Échéancier prévu</dt><dd>${money(sum('client'))}</dd>
      <dt>Encaissé</dt><dd class="pos">${money(sum('client', true))}</dd>
      <dt>Reste à encaisser</dt><dd><b>${money(sum('client', false))}</b></dd></dl></div>
    <div class="card"><h2>Fournisseurs</h2><dl class="kv">
      <dt>Coût d'achat (variante retenue)</dt><dd>${selTotal ? money(selTotal.achat) : '—'}</dd>
      <dt>Réglé</dt><dd>${money(sum('fournisseur', true))}</dd>
      <dt>Reste à payer</dt><dd><b>${money(sum('fournisseur', false))}</b></dd></dl></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Échéances</h2><div>
      <button class="btn small" data-act="gen-schedule" ${selTotal ? '' : 'disabled'}>⚡ Générer l'échéancier client</button>
      <button class="btn small" data-act="add-payment">+ Échéance</button></div></div>
    ${p.length ? `<table class="table"><thead><tr><th>Sens</th><th>Libellé</th><th>Fournisseur</th><th class="num">Montant</th><th>Échéance</th><th>Réglé</th><th>Mode</th><th></th></tr></thead><tbody>
      ${p.map((x) => `<tr data-payment="${x.id}" class="${!+x.paye && x.date_echeance && x.date_echeance < todayIso() ? 'late' : ''}">
        <td>${x.sens === 'client' ? '⬇ Client' : '⬆ Fournisseur'}</td><td>${esc(x.libelle)}</td><td>${esc(refLabel('suppliers', x.supplier_id))}</td>
        <td class="num">${money(x.montant)}</td><td>${date(x.date_echeance)}</td>
        <td><input type="checkbox" data-paid-toggle ${+x.paye ? 'checked' : ''} aria-label="Réglé"> ${x.date_paiement ? date(x.date_paiement) : ''}</td>
        <td>${esc(enumLabel('payments', 'mode', x.mode))}</td>
        <td class="tl-actions"><button class="icon-btn" data-act="edit-payment">✎</button><button class="icon-btn danger" data-act="del-payment">🗑</button></td></tr>`).join('')}
    </tbody></table>` : '<p class="muted">Aucune échéance. Générez l\'échéancier client (acompte + solde) à partir de la variante retenue.</p>'}
  </div>`;
}

/* ================================================================== Synchronisation des totaux */

let syncTimer = null;
function syncTotals() {
  const sel = data.options.find((o) => o.id === data.trip.selected_option_id);
  if (!sel) return;
  const t = computeOption(sel, ctx()).totals;
  const tr = data.trip;
  if (Math.abs((tr.total_vente || 0) - t.vente) < 0.01 && Math.abs((tr.total_achat || 0) - t.achat) < 0.01 && Math.abs((tr.marge || 0) - t.marge) < 0.01) return;
  clearTimeout(syncTimer);
  syncTimer = setTimeout(async () => {
    try {
      const u = await api.put(`trips/${tr.id}`, { total_vente: t.vente, total_achat: t.achat, marge: t.marge });
      Object.assign(data.trip, { total_vente: u.total_vente, total_achat: u.total_achat, marge: u.marge });
    } catch { /* silencieux : recalculé à la prochaine ouverture */ }
  }, 400);
}

/* ================================================================== Formulaires */

export async function itemDialog(values, { optionId, onSaved } = {}) {
  const isNew = !values.id;
  const type = values.type || 'vol';
  const title = isNew ? `Nouvelle prestation — ${state.schema.itemTypes[type]}` : `Modifier : ${values.libelle}`;
  const trip = data?.trip || {};
  if (isNew) {
    const defaults = { vol: 'personne', train: 'personne', hebergement: 'chambre_nuit', croisiere: 'personne', transfert: 'vehicule', location: 'jour', activite: 'personne', assurance: 'personne', visa: 'personne' };
    values = { unite: defaults[type] || 'forfait', devise: data?.settings?.devise_base || 'EUR', ...values };
    if (!values.date_debut && ['vol', 'hebergement', 'croisiere'].includes(type) && trip.date_depart) values.date_debut = trip.date_depart;
  }
  return modal({
    title, wide: true,
    body: `<div class="item-preview" id="item-preview"></div>${entityForm('items', values, { type })}`,
    onOpen: (form) => {
      enhanceForm(form);
      const toggle = () => {
        const t = form.elements.type.value;
        $$('[data-types]', form).forEach((elx) => { elx.hidden = !elx.dataset.types.split(' ').includes(t); });
      };
      const preview = () => {
        const v = readForm(form, 'items');
        const opt = currentOption() || { marge_mode: 'coef', marge_valeur: 0 };
        const c = computeOption({ ...opt, frais_dossier: 0, frais_dossier_pers: 0, arrondi: 0, marge_mode: opt.marge_mode === 'fixe_total' || opt.marge_mode === 'fixe_pers' ? 'coef' : opt.marge_mode, items: [{ ...v, optionnel: 0, statut: 'confirme' }] }, ctx());
        const l = c.lines[0];
        const dur = itemDuration(v);
        $('#item-preview', form).innerHTML = l ? `<b>Aperçu</b> · quantité ${+l.qty.toFixed(2)}${l.mult > 1 ? ` × ${l.mult} nuit(s)/jour(s)` : ''} · achat <b>${money(l.cost)}</b> · vente <b>${money(l.sell)}</b> · marge <b>${money(l.margin)}</b>${dur ? ` · durée ${fmtDuration(dur)}` : ''}${c.missingRates.length ? ` · <span class="neg">taux ${c.missingRates.join(', ')} manquant</span>` : ''}` : '';
      };
      const fillTz = (codeField, tzField) => {
        const a = airport(form.elements[codeField]?.value);
        if (a && form.elements[tzField] && !form.elements[tzField].value) form.elements[tzField].value = a.tz;
      };
      form.addEventListener('change', (e) => {
        const n = e.target.name;
        if (n === 'type') toggle();
        if (n === 'lieu_depart') fillTz('lieu_depart', 'tz_depart');
        if (n === 'lieu_arrivee') fillTz('lieu_arrivee', 'tz_arrivee');
        if (n === 'supplier_id' && e.target.value) {
          const s = (state.refs.suppliers || []).find((x) => x.id === +e.target.value);
          if (s?.commission_pct > 0 && !form.elements.commission_pct.value) {
            form.elements.commission_pct.value = String(s.commission_pct).replace('.', ',');
            form.elements.mode_tarif.value = 'commission';
          }
          if (s?.devise && form.elements.devise.value === (data?.settings?.devise_base || 'EUR')) form.elements.devise.value = s.devise;
          if (form.elements.compagnie && !form.elements.compagnie.value && ['aerien', 'croisiere', 'ferroviaire', 'loueur'].includes(s?.type)) form.elements.compagnie.value = s.nom;
        }
        if (n === 'date_debut' && form.elements.date_fin && !form.elements.date_fin.value && ['vol', 'train', 'transfert', 'activite'].includes(form.elements.type.value)) {
          form.elements.date_fin.value = e.target.value;
        }
        preview();
      });
      form.addEventListener('input', preview);
      // bouton création rapide de fournisseur
      const sel = form.elements.supplier_id;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn small inline-add';
      btn.textContent = '+ fournisseur';
      btn.onclick = async () => {
        const s = await quickCreate('suppliers');
        if (s) {
          sel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${esc(s.nom)}</option>`);
          sel.value = s.id;
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
      };
      sel.closest('label').appendChild(btn);
      ['lieu_depart', 'lieu_arrivee'].forEach((f) => form.elements[f]?.value && fillTz(f, f === 'lieu_depart' ? 'tz_depart' : 'tz_arrivee'));
      toggle();
      preview();
    },
    onSubmit: async (form) => {
      const body = readForm(form, 'items');
      if (body.devise) body.devise = body.devise.toUpperCase();
      // durée calculée automatiquement si non saisie
      if (!body.duree_min) {
        const d = itemDuration({ ...body, duree_min: null });
        if (d) body.duree_min = d;
      }
      const saved = isNew ? await api.post('items', { ...body, option_id: optionId }) : await api.put(`items/${values.id}`, body);
      onSaved && (await onSaved(saved));
      return saved;
    },
  });
}

async function optionDialog(opt) {
  return modal({
    title: opt?.id ? `Variante : ${opt.nom}` : 'Nouvelle variante', wide: true,
    body: entityForm('options', opt || { nom: `Variante ${String.fromCharCode(65 + data.options.length)}` }),
    onOpen: enhanceForm,
    onSubmit: async (form) => {
      const body = readForm(form, 'options');
      const r = opt?.id ? await api.put(`options/${opt.id}`, body) : await api.post('options', { ...body, trip_id: data.trip.id });
      ui.optionId = r.id;
      return r;
    },
  });
}

function simpleDialog(entity, values, extra, title) {
  return modal({
    title, wide: true, body: entityForm(entity, values), onOpen: enhanceForm,
    onSubmit: async (form) => {
      const body = { ...readForm(form, entity), ...extra };
      return values.id ? api.put(`${entity}/${values.id}`, body) : api.post(entity, body);
    },
  });
}

/* ================================================================== Événements */

async function onChange(e, el) {
  try {
    if (e.target.id === 'trip-status') {
      await api.put(`trips/${data.trip.id}`, { statut: e.target.value });
      data.trip.statut = e.target.value;
      toast('Statut mis à jour');
      if (e.target.value === 'confirme' && !data.payments.length) toast('Pensez à générer l\'échéancier (onglet Échéancier)', 'info', 6000);
    } else if (e.target.id === 'add-traveler' && e.target.value) {
      await api.post('travelers', { trip_id: data.trip.id, client_id: +e.target.value });
      await reload(el);
    } else if (e.target.matches('[data-paid-toggle]')) {
      const id = e.target.closest('[data-payment]').dataset.payment;
      await api.put(`payments/${id}`, { paye: e.target.checked ? 1 : 0, date_paiement: e.target.checked ? todayIso() : null });
      await reload(el);
    }
  } catch (err) {
    errorToast(err);
  }
}

async function onClick(e, el) {
  const tab = e.target.closest('[data-tab]');
  if (tab) {
    ui.tab = tab.dataset.tab;
    return draw(el);
  }
  const optBtn = e.target.closest('[data-opt]');
  if (optBtn) {
    ui.optionId = +optBtn.dataset.opt;
    ui.aiSuggestions = null;
    return draw(el);
  }
  const addType = e.target.closest('[data-add-type]');
  if (addType) {
    const r = await itemDialog({ type: addType.dataset.addType }, { optionId: ui.optionId });
    if (r) await reload(el);
    return;
  }
  const sg = e.target.closest('[data-suggest]');
  if (sg) {
    const [src, idx] = sg.dataset.suggest.split(':');
    const s = src === 'rule' ? ui.lastChecks[+idx] : ui.aiSuggestions[+idx];
    const prefill = src === 'rule' ? { ...s.add } : { type: s.type_prestation || 'autre', libelle: s.titre, notes: s.detail };
    if (!prefill.libelle && src === 'rule') prefill.libelle = state.schema.itemTypes[prefill.type];
    const r = await itemDialog(prefill, { optionId: ui.optionId });
    if (r) await reload(el);
    return;
  }
  const link = e.target.closest('[data-item-link]');
  if (link) {
    e.preventDefault();
    const it = currentOption().items.find((x) => x.id === +link.dataset.itemLink);
    if (it && (await itemDialog(it))) await reload(el);
    return;
  }

  const act = e.target.closest('[data-act]')?.dataset.act;
  if (!act) return;
  const itemId = +e.target.closest('[data-item]')?.dataset.item;
  const dayId = +e.target.closest('[data-day]')?.dataset.day;
  const payId = +e.target.closest('[data-payment]')?.dataset.payment;
  const opt = currentOption();
  const t = data.trip;

  try {
    switch (act) {
      case 'edit-trip':
        if (await simpleDialog('trips', t, {}, 'Modifier le dossier')) { await refreshRefs('clients', 'destinations'); await reload(el); }
        break;
      case 'dup-trip': {
        if (!(await confirmDialog('Créer une copie de ce dossier (variantes, prestations, programme) pour un nouveau client ou de nouvelles dates ?', { label: 'Dupliquer' }))) break;
        const c = await api.post(`trips/${t.id}/duplicate`);
        toast('Dossier dupliqué');
        navigate(`#/trips/${c.id}`);
        break;
      }
      case 'del-trip':
        if (await confirmDialog(`Supprimer définitivement le dossier ${t.reference} et tout son contenu ?`, { label: 'Supprimer' })) {
          await api.del(`trips/${t.id}`);
          toast('Dossier supprimé');
          navigate('#/trips');
        }
        break;
      case 'add-option':
        if (await optionDialog(null)) await reload(el);
        break;
      case 'edit-option':
        if (await optionDialog(opt)) await reload(el);
        break;
      case 'dup-option': {
        const c = await api.post(`options/${opt.id}/duplicate`);
        ui.optionId = c.id;
        toast('Variante dupliquée : modifiez-la pour comparer');
        await reload(el);
        break;
      }
      case 'del-option':
        if (await confirmDialog(`Supprimer la variante « ${opt.nom} » et ses prestations ?`, { label: 'Supprimer' })) {
          await api.del(`options/${opt.id}`);
          ui.optionId = null;
          await reload(el);
        }
        break;
      case 'select-option':
        await api.put(`trips/${t.id}`, { selected_option_id: opt.id });
        toast('Variante retenue');
        await reload(el);
        break;
      case 'edit-item': {
        const it = opt.items.find((x) => x.id === itemId);
        if (await itemDialog(it)) await reload(el);
        break;
      }
      case 'dup-item':
        await api.post(`items/${itemId}/duplicate`);
        await reload(el);
        break;
      case 'del-item':
        if (await confirmDialog('Supprimer cette prestation ?', { label: 'Supprimer' })) {
          await api.del(`items/${itemId}`);
          await reload(el);
        }
        break;
      case 'ai-import':
        if (await openAiImport({ trip: t, optionId: opt.id })) await reload(el);
        break;
      case 'ai-off':
        toast('Assistant IA non configuré : ajoutez votre clé API Anthropic dans config.php (voir Réglages > Assistant IA).', 'info', 8000);
        break;
      case 'ai-suggest': {
        const btn = e.target.closest('button');
        btn.disabled = true;
        btn.textContent = '✨ Analyse en cours…';
        try {
          ui.aiSuggestions = await api.post('ai/suggest', { summary: aiSummary(data, opt, computeOption(opt, ctx())) });
          draw(el);
        } finally {
          btn.disabled = false;
        }
        break;
      }
      case 'gen-days': {
        const days = generateDays(t, opt);
        if (!days.length) { toast('Renseignez les dates du voyage ou des prestations', 'error'); break; }
        if ((opt.days || []).length && !(await confirmDialog(`Remplacer les ${opt.days.length} jours existants par ${days.length} jours générés ?`, { label: 'Remplacer' }))) break;
        for (const d of opt.days || []) await api.del(`days/${d.id}`);
        for (const d of days) await api.post('days', { ...d, option_id: opt.id });
        toast('Programme généré : personnalisez les descriptions');
        await reload(el);
        break;
      }
      case 'add-day':
        if (await simpleDialog('days', { jour: (opt.days || []).length + 1 }, { option_id: opt.id }, 'Nouvelle journée')) await reload(el);
        break;
      case 'edit-day':
        if (await simpleDialog('days', opt.days.find((d) => d.id === dayId), {}, 'Modifier la journée')) await reload(el);
        break;
      case 'del-day':
        await api.del(`days/${dayId}`);
        await reload(el);
        break;
      case 'new-traveler': {
        const c = await quickCreate('clients');
        if (c) { await api.post('travelers', { trip_id: t.id, client_id: c.id }); await reload(el); }
        break;
      }
      case 'del-traveler':
        await api.del(`travelers/${+e.target.closest('[data-traveler]').dataset.traveler}`);
        await reload(el);
        break;
      case 'add-payment':
        if (await simpleDialog('payments', {}, { trip_id: t.id }, 'Nouvelle échéance')) await reload(el);
        break;
      case 'edit-payment':
        if (await simpleDialog('payments', data.payments.find((p) => p.id === payId), {}, 'Modifier l\'échéance')) await reload(el);
        break;
      case 'del-payment':
        if (await confirmDialog('Supprimer cette échéance ?', { label: 'Supprimer' })) { await api.del(`payments/${payId}`); await reload(el); }
        break;
      case 'gen-schedule': {
        const sel = data.options.find((o) => o.id === t.selected_option_id);
        const total = computeOption(sel, ctx()).totals.vente;
        const lines = paymentSchedule(total, { acomptePct: data.settings.acompte_pct, soldeJours: +data.settings.solde_jours, depart: t.date_depart, today: todayIso() });
        const ok = await confirmDialog(`Créer ${lines.length} échéance(s) client : ${lines.map((l) => `${l.libelle} ${money(l.montant)}`).join(' + ')} ?`, { label: 'Créer' });
        if (!ok) break;
        for (const l of lines) await api.post('payments', { ...l, trip_id: t.id, sens: 'client' });
        await reload(el);
        break;
      }
    }
  } catch (err) {
    errorToast(err);
  }
}
