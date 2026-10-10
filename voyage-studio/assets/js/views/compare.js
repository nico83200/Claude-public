// Comparateur de variantes : prix, rentabilité, durées, confort — vue agent ou vue client imprimable.

import { api } from '../api.js';
import { esc, money, pct, date, state, ITEM_ICONS, enumLabel, nl2br } from '../ui.js';
import { computeOption } from '../pricing.js';
import { fmtDuration, sortChrono } from '../time.js';

const eur = (v) => money(v);

let clientView = false;

export async function render(el, [id]) {
  const data = await api.get(`trips/${id}/full`);
  const ctx = { trip: data.trip, settings: data.settings, currencies: data.currencies, horsUE: !!+data.destination?.hors_ue };
  const cols = data.options.map((o) => ({ o, c: computeOption(o, ctx) }));

  const draw = () => {
    // [libellé, valeur(c,o), formateur, sens du meilleur ('min'|'max'|null), agentOnly]
    const rows = [
      ['Prix total', (c) => c.totals.vente, eur, 'min'],
      ['Prix par personne', (c) => c.totals.parPers, eur, 'min'],
      ['Prix / pers. / nuit', (c) => c.totals.parPersNuit, eur, 'min'],
      ['Écart au budget', (c) => c.totals.budgetEcart, (v) => (v === null ? '—' : (v > 0 ? '+' : '') + money(v)), 'min'],
      ['Prestations en option', (c) => c.totals.optionnel, (v) => (v ? money(v) : '—'), null],
      ['—'],
      ['Durée', (c) => c.stats.days, (v, c) => (v ? `${v} j / ${c.stats.nights} n` : '—'), null],
      ['Temps de vol total', (c) => c.stats.flightMin || null, fmtDuration, 'min'],
      ['Escales', (c) => (c.stats.flights ? c.stats.stops : null), (v) => (v === null ? '—' : v), 'min'],
      ['Transferts', (c) => c.stats.transfers, (v, c) => (v ? `${v} (${fmtDuration(c.stats.transferMin)})` : '—'), null],
      ['Temps de transport', (c) => c.stats.travelMin || null, fmtDuration, 'min'],
      ['Hébergement', (c) => c.stats.categories.join(', ') || '—', (v) => esc(v), null],
      ['Formule', (c) => c.stats.boards.map((b) => enumLabel('items', 'pension', b)).join(', ') || '—', (v) => esc(v), null],
      ['Prestations', (c) => c.included.length, (v) => v, null],
      ['—', null, null, null, true],
      ['Total achats', (c) => c.totals.achat, eur, null, true],
      ['Marge brute', (c) => c.totals.marge, eur, 'max', true],
      ['Taux de marque', (c) => c.totals.tauxMarque, (v) => pct(v), 'max', true],
      ['Marge nette (après TVA)', (c) => c.totals.margeNette, eur, 'max', true],
      ['Restant à réserver', (c) => c.stats.toBook, (v) => v, 'min', true],
    ].filter((r) => !(clientView && r[4]));

    const body = rows.map(([label, get, fmt, best]) => {
      if (label === '—') return `<tr class="sep"><td colspan="${cols.length + 1}"></td></tr>`;
      const vals = cols.map(({ c, o }) => get(c, o));
      const nums = vals.filter((v) => typeof v === 'number' && !isNaN(v));
      const target = best && nums.length > 1 ? (best === 'min' ? Math.min(...nums) : Math.max(...nums)) : null;
      return `<tr><th>${esc(label)}</th>${vals.map((v, i) => `<td class="${target !== null && v === target ? 'best' : ''}">${fmt(v, cols[i].c)}</td>`).join('')}</tr>`;
    }).join('');

    const maxTotal = Math.max(1, ...cols.map(({ c }) => c.totals.vente));
    const types = [...new Set(cols.flatMap(({ c }) => Object.keys(c.byType)))];
    const palette = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#db2777', '#059669', '#64748b', '#dc2626', '#0891b2', '#65a30d', '#a16207'];

    el.innerHTML = `
      <div class="page-head no-print">
        <div><a href="#/trips/${id}" class="muted">← ${esc(data.trip.reference)}</a><h1>Comparatif des variantes</h1></div>
        <div class="actions">
          <label class="switch"><input type="checkbox" id="client-view" ${clientView ? 'checked' : ''}> Vue client (sans marges)</label>
          <button class="btn" data-print>🖨 Imprimer / PDF</button>
        </div>
      </div>
      ${clientView ? `<div class="print-header"><h1>${esc(data.trip.titre)}</h1><p>${esc(state.settings.agence_nom)} — comparatif établi le ${date(new Date().toISOString().slice(0, 10))}</p></div>` : ''}
      <div class="card flush scroll-x">
        <table class="table compare">
          <thead><tr><th></th>${cols.map(({ o }) => `<th>${o.id === data.trip.selected_option_id ? '★ ' : ''}${esc(o.nom)}${o.resume ? `<div class="muted small">${nl2br(o.resume)}</div>` : ''}</th>`).join('')}</tr></thead>
          <tbody>${body}</tbody>
        </table>
      </div>
      <div class="card">
        <h2>Répartition du prix</h2>
        ${cols.map(({ o, c }) => `<div class="stack-row"><span class="stack-label">${esc(o.nom)}</span>
          <div class="stack" style="width:${(c.totals.vente / maxTotal) * 100}%">${types.map((t, i) => c.byType[t] ? `<i style="flex:${c.byType[t].vente};background:${palette[i % palette.length]}" title="${esc(state.schema.itemTypes[t])} : ${money(c.byType[t].vente)}"></i>` : '').join('')}</div>
          <b>${money(c.totals.vente, null, false)}</b></div>`).join('')}
        <p class="legend">${types.map((t, i) => `<span class="sw" style="background:${palette[i % palette.length]}"></span>${ITEM_ICONS[t]} ${esc(state.schema.itemTypes[t])}`).join(' ')}</p>
      </div>
      <div class="grid-${Math.min(cols.length, 3)}">
        ${cols.map(({ o, c }) => `<div class="card"><h3>${esc(o.nom)}</h3><ul class="plain">${sortChrono(c.included.map((l) => l.item)).map((i) => c.included.find((l) => l.item === i)).map((l) => `<li>${ITEM_ICONS[l.item.type]} ${esc(l.item.libelle)}${l.item.date_debut ? ` <span class="muted">· ${date(l.item.date_debut)}</span>` : ''}</li>`).join('')}
          ${c.optional.map((l) => `<li class="muted">➕ ${esc(l.item.libelle)} (option ${money(l.sell, null, false)})</li>`).join('')}</ul>
          <a class="btn small no-print" href="#/trips/${id}/quote/${o.id}">📄 Devis</a></div>`).join('')}
      </div>`;
    el.querySelector('#client-view').onchange = (e) => { clientView = e.target.checked; draw(); };
    el.onclick = (e) => e.target.closest('[data-print]') && window.print();
  };
  draw();
}
