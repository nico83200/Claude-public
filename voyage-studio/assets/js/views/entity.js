// Fiche détaillée (client / fournisseur / destination) : édition + éléments liés.

import { api } from '../api.js';
import { navigate, refreshRefs } from '../app.js';
import { $, esc, state, entityForm, enhanceForm, readForm, toast, confirmDialog, showFieldErrors, money, date, badge, errorToast } from '../ui.js';
import { localTimeIn, tzDiffHours, todayIso, addDays } from '../time.js';
import { newTripDialog } from './trips.js';

export async function render(el, [entity, id]) {
  const def = state.schema.entities[entity];
  const rec = await api.get(`${entity}/${id}`);
  const title = def.title.map((f) => rec[f]).filter(Boolean).join(' ');

  let related = '';
  if (entity === 'clients') {
    const trips = await api.get('trips', { client_id: id });
    const asTraveler = await api.get('travelers', { client_id: id });
    const otherIds = asTraveler.map((t) => t.trip_id).filter((tid) => !trips.some((t) => t.id === tid));
    const others = otherIds.length ? await api.get('trips', { id: otherIds }) : [];
    const all = [...trips, ...others];
    const ca = trips.filter((t) => ['confirme', 'solde', 'termine'].includes(t.statut)).reduce((s, t) => s + (t.total_vente || 0), 0);
    const passWarn = rec.passeport_expiration && rec.passeport_expiration < addDays(todayIso(), 182);
    related = `<div class="card"><div class="card-head"><h2>Voyages (${all.length})</h2><button class="btn small primary" data-act="new-trip">+ Nouveau dossier</button></div>
      <p class="muted">CA total réalisé : <b>${money(ca)}</b>${passWarn ? ` · <span class="neg">⚠️ passeport expirant le ${date(rec.passeport_expiration)}</span>` : ''}</p>
      ${all.length ? `<table class="table clickable"><tbody>${all.map((t) => `<tr data-href="#/trips/${t.id}"><td class="mono">${esc(t.reference)}</td><td>${esc(t.titre)}${t.client_id !== +id ? ' <span class="muted small">(voyageur)</span>' : ''}</td><td>${date(t.date_depart)}</td><td>${badge('trips', 'statut', t.statut)}</td><td class="num">${t.total_vente ? money(t.total_vente) : ''}</td></tr>`).join('')}</tbody></table>` : '<p class="muted">Aucun voyage.</p>'}</div>`;
  } else if (entity === 'destinations') {
    const trips = await api.get('trips', { destination_id: id });
    const tz = rec.fuseau;
    const diff = tz ? tzDiffHours('Europe/Paris', tz) : null;
    related = `<div class="card"><h2>En pratique</h2><dl class="kv">
      ${tz ? `<dt>Heure locale</dt><dd>${esc(localTimeIn(tz))} (${diff >= 0 ? '+' : ''}${String(diff).replace('.', ',')} h / Paris)</dd>` : ''}
      ${rec.devise ? `<dt>Devise</dt><dd>${esc(rec.devise)}${(() => { const c = state.currencies.find((x) => x.code === rec.devise); return c ? ` · 1 € = ${String(c.taux).replace('.', ',')} ${esc(rec.devise)}` : ''; })()}</dd>` : ''}
      ${rec.budget_jour ? `<dt>Budget sur place</dt><dd>${money(rec.budget_jour, null, false)} /pers./jour</dd>` : ''}
      </dl></div>
      <div class="card"><h2>Dossiers (${trips.length})</h2>${trips.length ? `<table class="table clickable"><tbody>${trips.map((t) => `<tr data-href="#/trips/${t.id}"><td class="mono">${esc(t.reference)}</td><td>${esc(t.titre)}</td><td>${date(t.date_depart)}</td><td>${badge('trips', 'statut', t.statut)}</td></tr>`).join('')}</tbody></table>` : '<p class="muted">Aucun dossier.</p>'}</div>`;
  } else if (entity === 'suppliers') {
    const items = await api.get('items', { supplier_id: id, limit: 200 });
    related = `<div class="card"><h2>Prestations récentes (${items.length})</h2>${items.length ? `<ul class="plain">${items.slice(0, 30).map((i) => `<li>${esc(i.libelle)} <span class="muted small">${date(i.date_debut)} · ${badge('items', 'statut', i.statut)}</span></li>`).join('')}</ul>` : '<p class="muted">Aucune prestation liée.</p>'}</div>`;
  }

  el.innerHTML = `
    <div class="page-head"><div><a href="#/${entity}" class="muted">← ${esc(def.label)}</a><h1>${esc(title)}</h1></div>
      <div class="actions"><button class="btn danger ghost" data-act="delete">Supprimer</button></div></div>
    <div class="grid-2 wide-left">
      <form class="card" id="edit-form">${entityForm(entity, rec)}<div class="form-actions"><button class="btn primary" type="submit">Enregistrer</button></div></form>
      <div class="col">${related}</div>
    </div>`;
  const form = $('#edit-form', el);
  enhanceForm(form);
  form.onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api.put(`${entity}/${id}`, readForm(form, entity));
      await refreshRefs(entity);
      toast('Enregistré');
    } catch (err) {
      showFieldErrors(form, err);
    }
  };
  el.onclick = async (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr) return navigate(tr.dataset.href);
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (act === 'new-trip') return newTripDialog({ client_id: +id });
    if (act === 'delete' && (await confirmDialog(`Supprimer « ${title} » ?`, { label: 'Supprimer' }))) {
      try {
        await api.del(`${entity}/${id}`);
        await refreshRefs(entity);
        navigate(`#/${entity}`);
      } catch (err) {
        errorToast(err);
      }
    }
  };
}
