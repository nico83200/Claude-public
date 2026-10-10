// Devis / proposition client imprimable (Imprimer → « Enregistrer en PDF » du navigateur).

import { api } from '../api.js';
import { esc, nl2br, money, date, ITEM_ICONS, enumLabel, state } from '../ui.js';
import { computeOption } from '../pricing.js';
import { fmtDuration, itemDuration, daysBetween, todayIso, sortChrono } from '../time.js';
import { airport } from '../airports.js';

let opts = { detail: false, showPrices: false };

export async function render(el, [tripId, optionId]) {
  const data = await api.get(`trips/${tripId}/full`);
  const opt = data.options.find((o) => o.id === +optionId) || data.options[0];
  const s = data.settings;
  const t = data.trip;
  const c = computeOption(opt, { trip: t, settings: s, currencies: data.currencies, horsUE: !!+data.destination?.hors_ue });
  const nights = daysBetween(t.date_depart, t.date_retour);
  const cl = data.client;
  const place = (code) => {
    const a = airport(code);
    return a ? `${a.name} (${a.code})` : code || '';
  };

  const itemLine = (l) => {
    const i = l.item;
    const bits = [];
    if (i.type === 'vol' || i.type === 'train') {
      bits.push(`${place(i.lieu_depart)}${i.heure_debut ? ` ${i.heure_debut}` : ''} → ${place(i.lieu_arrivee)}${i.heure_fin ? ` ${i.heure_fin}` : ''}`);
      if (i.compagnie || i.numero) bits.push([i.compagnie, i.numero].filter(Boolean).join(' '));
      const d = itemDuration(i);
      if (d) bits.push(`durée ${fmtDuration(d)}`);
      if (i.type === 'vol') bits.push(+i.escales ? `${i.escales} escale(s)` : 'vol direct');
      if (i.classe) bits.push(i.classe);
      if (i.bagages) bits.push(`bagages : ${i.bagages}`);
    } else {
      if (i.navire) bits.push(i.navire);
      if (i.categorie) bits.push(i.categorie);
      if (i.chambre) bits.push(i.chambre);
      if (i.pension) bits.push(enumLabel('items', 'pension', i.pension));
      if (i.mode_transfert) bits.push(enumLabel('items', 'mode_transfert', i.mode_transfert));
      if (i.date_fin && i.date_debut && i.date_fin > i.date_debut) bits.push(`${daysBetween(i.date_debut, i.date_fin)} nuit(s), du ${date(i.date_debut)} au ${date(i.date_fin)}`);
    }
    return `<tr><td class="q-ico">${ITEM_ICONS[i.type] || ''}</td><td>${i.date_debut && !(i.date_fin > i.date_debut && !['vol', 'train'].includes(i.type)) ? `<span class="q-date">${date(i.date_debut)}</span> ` : ''}<b>${esc(i.libelle)}</b>
      ${bits.length ? `<div class="muted">${esc(bits.join(' · '))}</div>` : ''}${i.description ? `<div>${nl2br(i.description)}</div>` : ''}</td>
      ${opts.showPrices ? `<td class="num">${money(l.sell)}</td>` : ''}</tr>`;
  };

  const payments = data.payments.filter((p) => p.sens === 'client');
  const validity = t.date_option_limite;

  el.innerHTML = `
    <div class="quote-toolbar no-print">
      <a href="#/trips/${t.id}" class="btn">← Retour au dossier</a>
      <label class="switch"><input type="checkbox" id="q-prices" ${opts.showPrices ? 'checked' : ''}> Prix par prestation</label>
      <select id="q-opt">${data.options.map((o) => `<option value="${o.id}" ${o.id === opt.id ? 'selected' : ''}>${esc(o.nom)}</option>`).join('')}</select>
      <button class="btn primary" data-print>🖨 Imprimer / Enregistrer en PDF</button>
    </div>
    <article class="quote" style="--brand:${esc(s.agence_couleur || '#0f766e')}">
      <header class="q-head">
        <div class="q-agency">
          ${s.agence_logo ? `<img src="${esc(s.agence_logo)}" alt="" class="q-logo">` : ''}
          <div><b>${esc(s.agence_nom)}</b><br>${nl2br(s.agence_adresse)}<br>${esc(s.agence_telephone)} ${s.agence_email ? '· ' + esc(s.agence_email) : ''}${s.agence_site ? '<br>' + esc(s.agence_site) : ''}</div>
        </div>
        <div class="q-client">
          <div class="q-doc">Proposition de voyage<br><span class="mono">${esc(t.reference)}</span></div>
          <p>Établie le ${date(todayIso())}${validity ? `<br>Valable jusqu'au ${date(validity)}` : ''}</p>
          ${cl ? `<p><b>${esc(cl.civilite || '')} ${esc(cl.prenom || '')} ${esc(cl.nom)}</b><br>${nl2br(cl.adresse || '')}${cl.email ? '<br>' + esc(cl.email) : ''}</p>` : ''}
        </div>
      </header>

      <section class="q-hero">
        <h1>${esc(t.titre)}</h1>
        <p class="q-sub">${esc(opt.nom)}</p>
        <div class="q-facts">
          ${t.date_depart ? `<div><span>Dates</span><b>${date(t.date_depart)} → ${date(t.date_retour)}</b></div>` : ''}
          ${nights ? `<div><span>Durée</span><b>${nights + 1} jours / ${nights} nuits</b></div>` : ''}
          <div><span>Voyageurs</span><b>${t.nb_adultes || 0} adulte(s)${+t.nb_enfants ? `, ${t.nb_enfants} enfant(s)` : ''}${+t.nb_bebes ? `, ${t.nb_bebes} bébé(s)` : ''}</b></div>
          ${t.ville_depart ? `<div><span>Au départ de</span><b>${esc(t.ville_depart)}</b></div>` : ''}
        </div>
        ${s.devis_intro ? `<p>${nl2br(s.devis_intro)}</p>` : ''}
        ${opt.resume ? `<p>${nl2br(opt.resume)}</p>` : ''}
        ${data.destination?.description ? `<p>${nl2br(data.destination.description)}</p>` : ''}
      </section>

      ${(opt.days || []).length ? `<section><h2>Votre programme</h2><ol class="q-days">${opt.days.map((d) => `<li><div class="q-dn">Jour ${d.jour}${t.date_depart ? `<small>${date(new Date(Date.parse(t.date_depart) + (d.jour - 1) * 86400000).toISOString().slice(0, 10))}</small>` : ''}</div>
        <div><b>${esc(d.titre)}</b>${d.description ? `<p>${nl2br(d.description)}</p>` : ''}${d.lieu || d.repas ? `<p class="muted">${d.lieu ? `Nuit : ${esc(d.lieu)}` : ''}${d.repas ? ` · Repas : ${esc(d.repas)}` : ''}</p>` : ''}</div></li>`).join('')}</ol></section>` : ''}

      <section><h2>Vos prestations</h2>
        <table class="q-table">${sortChrono(c.included.map((l) => l.item)).map((i) => itemLine(c.included.find((l) => l.item === i))).join('')}</table>
      </section>

      <section class="q-price">
        <h2>Tarif</h2>
        <div class="q-price-box">
          <div><span>Prix par personne</span><strong>${money(c.totals.parPers)}</strong><small>sur la base de ${c.totals.pax} personne(s) payante(s)</small></div>
          <div><span>Prix total</span><strong>${money(c.totals.vente)}</strong><small>TTC${s.tva_regime === 'franchise' ? ' — TVA non applicable, art. 293 B du CGI' : ''}</small></div>
        </div>
        ${c.optional.length ? `<h3>En option</h3><table class="q-table">${c.optional.map((l) => `<tr><td class="q-ico">${ITEM_ICONS[l.item.type]}</td><td><b>${esc(l.item.libelle)}</b>${l.item.description ? `<div>${nl2br(l.item.description)}</div>` : ''}</td><td class="num">${money(l.sell)}</td></tr>`).join('')}</table>` : ''}
        <div class="q-cols">
          ${opt.inclus ? `<div><h3>Le prix comprend</h3><ul>${opt.inclus.split('\n').filter(Boolean).map((x) => `<li>${esc(x)}</li>`).join('')}</ul></div>` : ''}
          ${opt.non_inclus ? `<div><h3>Le prix ne comprend pas</h3><ul>${opt.non_inclus.split('\n').filter(Boolean).map((x) => `<li>${esc(x)}</li>`).join('')}</ul></div>` : ''}
        </div>
        ${payments.length ? `<h3>Échéancier</h3><table class="q-table">${payments.map((p) => `<tr><td>${esc(p.libelle)}</td><td>${p.date_echeance ? date(p.date_echeance) : ''}</td><td class="num">${money(p.montant)}</td></tr>`).join('')}</table>`
          : `<p>Acompte de ${esc(s.acompte_pct)} % à la réservation, solde ${esc(s.solde_jours)} jours avant le départ.</p>`}
      </section>

      ${data.destination?.formalites || data.destination?.sante ? `<section><h2>Formalités</h2>${data.destination.formalites ? `<p>${nl2br(data.destination.formalites)}</p>` : ''}${data.destination.sante ? `<p><b>Santé :</b> ${nl2br(data.destination.sante)}</p>` : ''}
        <p class="muted small">Informations données à titre indicatif à la date du devis, à vérifier sur diplomatie.gouv.fr avant le départ.</p></section>` : ''}

      <section class="q-cond"><h2>Conditions</h2><p>${nl2br(s.devis_conditions)}</p></section>

      <footer class="q-foot">
        ${esc(s.agence_nom)}${s.agence_siret ? ` · SIRET ${esc(s.agence_siret)}` : ''}${s.agence_immatriculation ? ` · Immatriculation Atout France ${esc(s.agence_immatriculation)}` : ''}
        ${s.agence_garantie ? `<br>Garantie financière : ${esc(s.agence_garantie)}` : ''}${s.agence_rcp ? ` · RC professionnelle : ${esc(s.agence_rcp)}` : ''}
      </footer>
    </article>`;

  el.onclick = (e) => e.target.closest('[data-print]') && window.print();
  el.onchange = (e) => {
    if (e.target.id === 'q-prices') { opts.showPrices = e.target.checked; render(el, [tripId, opt.id]); }
    if (e.target.id === 'q-opt') location.hash = `#/trips/${tripId}/quote/${e.target.value}`;
  };
  document.title = `Devis ${t.reference} — ${state.settings.agence_nom}`;
}
