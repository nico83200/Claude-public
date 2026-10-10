// Comparateur de destinations : saisonnalité, budget, durée de vol, décalage horaire, formalités.

import { api } from '../api.js';
import { navigate } from '../app.js';
import { esc, money, emptyState } from '../ui.js';
import { tzDiffHours } from '../time.js';

const MONTHS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

export async function render(el) {
  const dests = await api.get('destinations');
  let month = new Date().getMonth();
  let maxBudget = '';
  let horsUe = '';

  const draw = () => {
    const rows = dests
      .map((d) => ({ d, lvl: +((d.mois_ideaux || '').padEnd(12, '0')[month]), diff: d.fuseau ? tzDiffHours('Europe/Paris', d.fuseau) : null }))
      .filter((r) => (maxBudget ? !r.d.budget_jour || r.d.budget_jour <= +maxBudget : true))
      .filter((r) => (horsUe === '' ? true : String(+r.d.hors_ue) === horsUe))
      .sort((a, b) => b.lvl - a.lvl || (a.d.budget_jour || 0) - (b.d.budget_jour || 0));
    el.querySelector('#dc-body').innerHTML = rows.length ? `<table class="table clickable dest-compare">
      <thead><tr><th>Destination</th>${MONTHS.map((m, i) => `<th class="m-col ${i === month ? 'cur' : ''}" title="${m}">${m[0]}</th>`).join('')}<th>Vol</th><th>Décalage</th><th class="num">Budget/j</th><th>Formalités</th></tr></thead>
      <tbody>${rows.map(({ d, diff }) => `<tr data-href="#/destinations/${d.id}">
        <td><b>${esc(d.nom)}</b><br><span class="muted small">${esc(d.pays || '')}${+d.hors_ue ? ' · hors UE' : ''}</span></td>
        ${(d.mois_ideaux || '').padEnd(12, '0').split('').map((l, i) => `<td class="m-col heat lvl${l} ${i === month ? 'cur' : ''}"></td>`).join('')}
        <td>${esc(d.vol_duree || '—')}</td>
        <td>${diff === null ? '—' : `${diff > 0 ? '+' : ''}${String(diff).replace('.', ',')} h`}</td>
        <td class="num">${d.budget_jour ? money(d.budget_jour, null, false) : '—'}</td>
        <td class="small">${esc((d.formalites || '').slice(0, 90))}${(d.formalites || '').length > 90 ? '…' : ''}</td></tr>`).join('')}</tbody></table>`
      : emptyState('🌍', 'Aucune destination ne correspond.');
  };

  el.innerHTML = `
    <div class="page-head"><div><a href="#/destinations" class="muted">← Destinations</a><h1>Où partir ? Comparateur de destinations</h1></div></div>
    <div class="toolbar">
      <label>Mois de départ <select id="dc-month">${MONTHS.map((m, i) => `<option value="${i}" ${i === month ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
      <label>Budget max sur place (€/j) <input type="number" id="dc-budget" min="0" step="10" style="width:7em"></label>
      <label>Zone <select id="dc-ue"><option value="">Toutes</option><option value="0">Union européenne</option><option value="1">Hors UE</option></select></label>
      <span class="legend"><span class="sw lvl2"></span>idéal <span class="sw lvl1"></span>correct <span class="sw lvl0"></span>déconseillé</span>
    </div>
    <div class="card flush scroll-x" id="dc-body"></div>
    <p class="muted small">La saisonnalité se renseigne dans chaque fiche destination (cliquez sur les mois).</p>`;
  draw();
  el.onchange = (e) => {
    if (e.target.id === 'dc-month') month = +e.target.value;
    if (e.target.id === 'dc-ue') horsUe = e.target.value;
    draw();
  };
  el.oninput = (e) => {
    if (e.target.id === 'dc-budget') { maxBudget = e.target.value; draw(); }
  };
  el.onclick = (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr) navigate(tr.dataset.href);
  };
}
