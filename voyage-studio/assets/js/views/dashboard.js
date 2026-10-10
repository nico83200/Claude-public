import { api } from '../api.js';
import { esc, money, pct, date, emptyState, state, toast, errorToast } from '../ui.js';
import { computeOption } from '../pricing.js';

/** Calcule les totaux des dossiers jamais ouverts (import, démo, restauration) pour fiabiliser les indicateurs. */
async function healTotals() {
  const trips = await api.get('trips', { limit: 5000 });
  const todo = trips.filter((t) => t.selected_option_id && t.total_vente === null).slice(0, 30);
  for (const t of todo) {
    const d = await api.get(`trips/${t.id}/full`);
    const opt = d.options.find((o) => o.id === t.selected_option_id);
    if (!opt) continue;
    const c = computeOption(opt, { trip: d.trip, settings: d.settings, currencies: d.currencies, horsUE: !!+d.destination?.hors_ue }).totals;
    await api.put(`trips/${t.id}`, { total_vente: c.vente, total_achat: c.achat, marge: c.marge });
  }
}

const MONTHS = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];

export async function render(el) {
  await healTotals().catch(() => {});
  const d = await api.get('dashboard');
  const statusOpts = state.schema.entities.trips.fields.statut.options;
  const max = Math.max(1, ...d.months.map((m) => m.ca));
  const year = d.today.slice(0, 4);

  const list = (rows, fn, empty) => (rows.length ? `<ul class="alerts">${rows.map(fn).join('')}</ul>` : `<p class="muted pad">${empty}</p>`);

  el.innerHTML = `
    <div class="page-head"><h1>Tableau de bord</h1><span class="muted">${date(d.today, true)}</span></div>

    <section class="kpis">
      <div class="kpi"><span>CA ${year} (départs confirmés)</span><strong>${money(d.ca, null, false)}</strong></div>
      <div class="kpi"><span>Marge brute ${year}</span><strong>${money(d.marge, null, false)}</strong><small>${pct(d.taux_marge)} du CA</small></div>
      <div class="kpi"><span>Devis & options en cours</span><strong>${money(d.pipeline, null, false)}</strong><small>${(d.counts.devis || 0) + (d.counts.option || 0)} dossiers</small></div>
      <div class="kpi"><span>Taux de transformation</span><strong>${d.conversion === null ? '—' : d.conversion + ' %'}</strong><small>dossiers créés en ${year}</small></div>
    </section>

    <section class="grid-2">
      <div class="card">
        <h2>À traiter</h2>
        ${d.options.length + d.payments.length + d.documents.length + d.to_book.length === 0 ? emptyState('✅', 'Rien d\'urgent. Bon travail !') : ''}
        ${d.options.length ? `<h3>Options & validités (≤ 7 jours)</h3>${list(d.options, (o) => `<li class="${o.expire ? 'danger' : 'warn'}"><a href="#/trips/${o.trip_id}">${esc(o.trip)}</a><span>${esc(o.quoi)}</span><b>${o.expire ? 'expiré le ' : ''}${date(o.date)}</b></li>`, '')}` : ''}
        ${d.payments.length ? `<h3>Échéances de paiement (≤ 30 jours)</h3>${list(d.payments, (p) => `<li class="${p.retard ? 'danger' : ''}"><a href="#/trips/${p.trip_id}">${esc(p.trip)}</a><span>${p.sens === 'client' ? '⬇ Encaisser' : '⬆ Payer'} — ${esc(p.libelle)}</span><b>${money(p.montant)} · ${date(p.date)}</b>
            <button class="btn small" data-paid="${p.id}">Marquer réglé</button></li>`, '')}` : ''}
        ${d.to_book.length ? `<h3>Prestations à réserver sur dossiers confirmés</h3>${list(d.to_book, (i) => `<li class="warn"><a href="#/trips/${i.trip_id}">${esc(i.trip)}</a><span>${esc(i.libelle)}</span><b>${esc(state.schema.entities.items.fields.statut.options[i.statut])}</b></li>`, '')}` : ''}
        ${d.documents.length ? `<h3>Documents de voyage</h3>${list(d.documents, (a) => `<li class="warn"><a href="#/trips/${a.trip_id}">${esc(a.trip)}</a><span><a href="#/clients/${a.client_id}">${esc(a.client)}</a> — ${esc(a.message)}</span></li>`, '')}` : ''}
      </div>
      <div class="col">
        <div class="card">
          <h2>Prochains départs</h2>
          ${list(d.departures, (t) => `<li><a href="#/trips/${t.trip_id}">${esc(t.trip)}</a><b>${date(t.date)} · J-${t.j}</b></li>`, 'Aucun départ confirmé dans les 45 jours.')}
        </div>
        <div class="card">
          <h2>Dossiers par statut</h2>
          <div class="status-pills">${Object.entries(d.counts).map(([k, n]) => `<a href="#/trips?statut=${k}" class="pill">${esc(statusOpts[k] || k)} <b>${n}</b></a>`).join('')}</div>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>CA et marge par mois de départ — ${year}</h2>
      <div class="bars" role="img" aria-label="Chiffre d'affaires mensuel">
        ${d.months.map((m, i) => `<div class="bar-col" title="${MONTHS[i]} : CA ${money(m.ca)} / marge ${money(m.marge)}">
          <div class="bar"><div class="bar-ca" style="height:${(m.ca / max) * 100}%"></div><div class="bar-m" style="height:${(m.marge / max) * 100}%"></div></div>
          <span>${MONTHS[i]}</span></div>`).join('')}
      </div>
      <p class="legend"><span class="sw ca"></span> Chiffre d'affaires <span class="sw m"></span> Marge brute</p>
    </section>`;

  el.onclick = async (e) => {
    const b = e.target.closest('[data-paid]');
    if (!b) return;
    try {
      await api.put(`payments/${b.dataset.paid}`, { paye: 1, date_paiement: d.today });
      toast('Échéance marquée comme réglée');
      render(el);
    } catch (err) {
      errorToast(err);
    }
  };
}
