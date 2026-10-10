// Liste générique (clients, fournisseurs, destinations) pilotée par le schéma.

import { api } from '../api.js';
import { navigate, refreshRefs } from '../app.js';
import { $, esc, state, modal, entityForm, enhanceForm, readForm, emptyState, debounce, enumLabel, date } from '../ui.js';

const MONTHS = 'JFMAMJJASOND';

export function cell(entity, name, f, v) {
  if (v === null || v === undefined || v === '') return '';
  if (f.type === 'enum') return esc(enumLabel(entity, name, v));
  if (f.type === 'bool') return +v ? '✔' : '';
  if (f.type === 'date') return date(v);
  if (f.type === 'float') return esc(String(v).replace('.', ',')) + (name.endsWith('_pct') ? ' %' : '');
  return esc(v);
}

export async function render(el, [entity]) {
  const def = state.schema.entities[entity];
  const cols = Object.entries(def.fields).filter(([, f]) => f.list);
  el.innerHTML = `
    <div class="page-head"><h1>${esc(def.label)}</h1>
      <div class="actions">
        ${entity === 'destinations' ? '<a class="btn" href="#/destinations/compare">📅 Comparer & saisonnalité</a>' : ''}
        <button class="btn" id="export">Exporter (CSV)</button>
        <button class="btn primary" id="add">+ ${esc(def.singular)}</button></div></div>
    <div class="toolbar"><input type="search" id="q" placeholder="Rechercher…"></div>
    <div id="list" class="card flush"></div>`;

  const load = async (q = '') => {
    const rows = await api.get(entity, { q });
    $('#list', el).innerHTML = rows.length ? `<table class="table clickable">
      <thead><tr>${cols.map(([, f]) => `<th>${esc(f.label)}</th>`).join('')}${entity === 'destinations' ? '<th>Saison idéale</th>' : ''}</tr></thead>
      <tbody>${rows.map((r) => `<tr data-href="#/${entity}/${r.id}">${cols.map(([n, f]) => `<td>${cell(entity, n, f, r[n])}</td>`).join('')}
        ${entity === 'destinations' ? `<td><span class="mini-months">${(r.mois_ideaux || '').padEnd(12, '0').split('').map((l, i) => `<i class="lvl${l}" title="${MONTHS[i]}">${MONTHS[i]}</i>`).join('')}</span></td>` : ''}</tr>`).join('')}</tbody>
    </table>` : emptyState('📇', `Aucun élément. Ajoutez votre premier ${def.singular.toLowerCase()}.`);
  };
  await load();
  $('#q', el).oninput = debounce((e) => load(e.target.value), 250);
  $('#export', el).onclick = () => api.download(`export/${entity}`);
  $('#add', el).onclick = async () => {
    const r = await modal({
      title: `Nouveau : ${def.singular.toLowerCase()}`, wide: true, body: entityForm(entity, {}), onOpen: enhanceForm,
      onSubmit: (form) => api.post(entity, readForm(form, entity)),
    });
    if (r) {
      await refreshRefs(entity);
      navigate(`#/${entity}/${r.id}`);
    }
  };
  el.onclick = (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr) navigate(tr.dataset.href);
  };
}
