import { api } from '../api.js';
import { refreshRefs, navigate, hashParams } from '../app.js';
import { $, esc, money, date, badge, state, modal, entityForm, enhanceForm, readForm, emptyState, debounce } from '../ui.js';

export async function render(el) {
  const params = hashParams();
  const statuses = state.schema.entities.trips.fields.statut.options;
  let filter = { statut: params.get('statut') || '', q: '' };

  el.innerHTML = `
    <div class="page-head"><h1>Dossiers</h1>
      <div class="actions"><button class="btn" id="export">Exporter (CSV)</button><button class="btn primary" id="add">+ Nouveau dossier</button></div></div>
    <div class="toolbar">
      <input type="search" id="q" placeholder="Filtrer (référence, intitulé, demande…)">
      <select id="statut"><option value="">Tous les statuts actifs</option><option value="*">Tous</option>
        ${Object.entries(statuses).map(([k, l]) => `<option value="${k}" ${filter.statut === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>
    </div>
    <div id="list" class="card flush"></div>`;

  const load = async () => {
    const query = { q: filter.q };
    if (filter.statut && filter.statut !== '*') query.statut = filter.statut;
    let rows = await api.get('trips', query);
    if (!filter.statut) rows = rows.filter((r) => !['termine', 'annule'].includes(r.statut));
    $('#list', el).innerHTML = rows.length ? `
      <table class="table clickable">
        <thead><tr><th>Réf.</th><th>Voyage</th><th>Client</th><th>Départ</th><th>Statut</th><th class="num">Prix de vente</th><th class="num">Marge</th></tr></thead>
        <tbody>${rows.map((t) => `<tr data-href="#/trips/${t.id}">
          <td class="mono">${esc(t.reference)}</td>
          <td><b>${esc(t.titre)}</b>${t._destination ? `<br><span class="muted small">${esc(t._destination)}</span>` : ''}</td>
          <td>${esc(t._client || '')}</td>
          <td>${date(t.date_depart)}</td>
          <td>${badge('trips', 'statut', t.statut)}</td>
          <td class="num">${t.total_vente ? money(t.total_vente) : '—'}</td>
          <td class="num">${t.marge ? money(t.marge) : '—'}</td></tr>`).join('')}</tbody>
      </table>` : emptyState('🧳', 'Aucun dossier pour ce filtre.', '<button class="btn primary" data-new>Créer un dossier</button>');
  };
  await load();

  $('#q', el).oninput = debounce((e) => { filter.q = e.target.value; load(); }, 250);
  $('#statut', el).onchange = (e) => { filter.statut = e.target.value; load(); };
  $('#export', el).onclick = () => api.download('export/trips');
  el.onclick = (e) => {
    if (e.target.closest('#add,[data-new]')) return newTripDialog();
    const tr = e.target.closest('tr[data-href]');
    if (tr) navigate(tr.dataset.href);
  };
}

export async function newTripDialog(values = {}) {
  await modal({
    title: 'Nouveau dossier',
    wide: true,
    body: `<p class="muted small">Le client et la destination peuvent être créés à la volée avec les boutons « + ».</p>${entityForm('trips', values, { exclude: ['reference'] })}`,
    submitLabel: 'Créer le dossier',
    onOpen: (form) => {
      enhanceForm(form);
      for (const [field, entity, label] of [['client_id', 'clients', 'client'], ['destination_id', 'destinations', 'destination']]) {
        const sel = form.elements[field];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn small inline-add';
        btn.textContent = `+ ${label}`;
        btn.onclick = async () => {
          const created = await quickCreate(entity);
          if (created) {
            sel.insertAdjacentHTML('beforeend', `<option value="${created.id}">${esc(created.nom)} ${esc(created.prenom || '')}</option>`);
            sel.value = created.id;
          }
        };
        sel.closest('label').appendChild(btn);
      }
    },
    onSubmit: async (form) => {
      const trip = await api.post('trips', readForm(form, 'trips'));
      navigate(`#/trips/${trip.id}`);
      return trip;
    },
  });
}

export async function quickCreate(entity) {
  const def = state.schema.entities[entity];
  return modal({
    title: `Nouveau : ${def.singular.toLowerCase()}`,
    wide: true,
    body: entityForm(entity, {}),
    onOpen: enhanceForm,
    onSubmit: async (form) => {
      const r = await api.post(entity, readForm(form, entity));
      await refreshRefs(entity);
      return r;
    },
  });
}
