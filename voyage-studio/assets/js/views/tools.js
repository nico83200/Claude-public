// Boîte à outils : calculatrice de prix, convertisseur, durées de vol & décalages, comparateur rapide d'offres.

import { esc, money, pct, state } from '../ui.js';
import { sellFromCost, vatOnMargin, makeConverter } from '../pricing.js';
import { durationBetween, fmtDuration, tzDiffHours, zonedToUtc } from '../time.js';
import { airport, AIRPORTS } from '../airports.js';

const LS_KEY = 'vs-quick-compare';
const n = (v) => +String(v ?? '').replace(/\s/g, '').replace(',', '.') || 0;

function loadOffers() {
  try {
    return JSON.parse(localStorage.getItem(LS_KEY)) || null;
  } catch {
    return null;
  }
}
function saveOffers(o) {
  try {
    localStorage.setItem(LS_KEY, JSON.stringify(o));
  } catch { /* stockage indisponible */ }
}

export async function render(el) {
  const s = state.settings;
  const curOpts = state.currencies.map((c) => `<option ${c.code === (s.devise_base || 'EUR') ? 'selected' : ''}>${esc(c.code)}</option>`).join('');
  let offers = loadOffers() || [
    { label: 'Offre A', prix: 1250, devise: 'EUR', duree: 11.5, escales: 0, bagage: true, confort: 4 },
    { label: 'Offre B', prix: 980, devise: 'EUR', duree: 15, escales: 1, bagage: true, confort: 3 },
  ];
  let weights = { prix: 50, duree: 25, escales: 15, confort: 10 };

  el.innerHTML = `
    <div class="page-head"><h1>Outils</h1></div>
    <datalist id="dl-ap">${AIRPORTS.map((a) => `<option value="${a.code}">${esc(a.name)}</option>`).join('')}</datalist>
    <div class="grid-2">
      <section class="card" id="calc">
        <h2>🧮 Prix de vente & marge</h2>
        <div class="form-grid">
          <label class="field"><span class="lbl">Coût d'achat</span><input name="cost" inputmode="decimal" value="1000"></label>
          <label class="field"><span class="lbl">Devise d'achat</span><select name="cur">${curOpts}</select></label>
          <label class="field"><span class="lbl">Mode</span><select name="mode"><option value="coef">Majoration %</option><option value="marque">Taux de marque %</option><option value="fixe">Montant fixe</option></select></label>
          <label class="field"><span class="lbl">Valeur</span><input name="val" inputmode="decimal" value="${esc(s.marge_valeur)}"></label>
          <label class="field"><span class="lbl">Voyageurs payants</span><input name="pax" type="number" min="1" value="2"></label>
          <label class="field check"><input type="checkbox" name="horsue"> <span>Voyage hors UE</span></label>
        </div>
        <div class="result" id="calc-out"></div>
        <h3>Prix public commissionné</h3>
        <div class="form-grid">
          <label class="field"><span class="lbl">Prix public</span><input name="pub" inputmode="decimal" value="2000"></label>
          <label class="field"><span class="lbl">Commission %</span><input name="com" inputmode="decimal" value="12"></label>
          <label class="field"><span class="lbl">Taxes non commissionnables</span><input name="tax" inputmode="decimal" value="0"></label>
        </div>
        <div class="result" id="com-out"></div>
      </section>

      <section class="card" id="fx">
        <h2>💱 Convertisseur de devises</h2>
        <div class="form-grid">
          <label class="field"><span class="lbl">Montant</span><input name="amt" inputmode="decimal" value="100"></label>
          <label class="field"><span class="lbl">De</span><select name="from">${state.currencies.map((c) => `<option ${c.code === 'USD' ? 'selected' : ''}>${esc(c.code)}</option>`).join('')}</select></label>
          <label class="field"><span class="lbl">Vers</span><select name="to">${curOpts}</select></label>
          <label class="field check"><input type="checkbox" name="safety" checked> <span>Inclure la sécurité de change (${esc(s.securite_change)} %)</span></label>
        </div>
        <div class="result" id="fx-out"></div>
        <p class="muted small">Taux : Réglages › Devises (mise à jour BCE en un clic).</p>

        <h2>🕑 Durée de vol & décalage horaire</h2>
        <div class="form-grid">
          <label class="field"><span class="lbl">Départ (IATA)</span><input name="ap1" list="dl-ap" value="CDG" maxlength="3" style="text-transform:uppercase"></label>
          <label class="field"><span class="lbl">Arrivée (IATA)</span><input name="ap2" list="dl-ap" value="BKK" maxlength="3" style="text-transform:uppercase"></label>
          <label class="field"><span class="lbl">Date / heure départ (locale)</span><span class="row"><input type="date" name="d1"><input type="time" name="t1" value="13:30"></span></label>
          <label class="field"><span class="lbl">Date / heure arrivée (locale)</span><span class="row"><input type="date" name="d2"><input type="time" name="t2" value="06:15"></span></label>
        </div>
        <div class="result" id="fl-out"></div>
      </section>
    </div>

    <section class="card" id="qc">
      <div class="card-head"><h2>⚖️ Comparateur rapide d'offres</h2><div><button class="btn small" data-qc="add">+ Offre</button> <button class="btn small ghost" data-qc="reset">Vider</button></div></div>
      <p class="muted small">Comparez en quelques secondes des vols, croisières ou séjours trouvés sur différents sites. Les données restent dans ce navigateur.</p>
      <div class="weights">${Object.keys(weights).map((k) => `<label>${{ prix: 'Prix', duree: 'Durée', escales: 'Escales', confort: 'Confort' }[k]} <input type="range" min="0" max="100" value="${weights[k]}" data-w="${k}"></label>`).join('')}</div>
      <div id="qc-table" class="scroll-x"></div>
    </section>`;

  const today = new Date().toISOString().slice(0, 10);
  el.querySelector('[name=d1]').value = today;
  el.querySelector('[name=d2]').value = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

  const val = (root, name) => root.querySelector(`[name=${name}]`);

  const calc = () => {
    const root = el.querySelector('#calc');
    const conv = makeConverter(state.currencies, s.devise_base || 'EUR', s.securite_change);
    const cost = conv(n(val(root, 'cost').value), val(root, 'cur').value);
    const sell = sellFromCost(cost, val(root, 'mode').value, n(val(root, 'val').value));
    const margin = sell - cost;
    const tva = s.tva_regime === 'marge' && !val(root, 'horsue').checked ? vatOnMargin(margin, s.tva_taux) : 0;
    const pax = Math.max(1, n(val(root, 'pax').value));
    el.querySelector('#calc-out').innerHTML = `<dl class="kv">
      <dt>Coût (devise de base)</dt><dd>${money(cost)}</dd><dt>Prix de vente</dt><dd><b>${money(sell)}</b> · ${money(sell / pax)} /pers.</dd>
      <dt>Marge brute</dt><dd>${money(margin)} · marque ${pct(sell ? (margin / sell) * 100 : 0)} · coef ${cost ? (sell / cost).toFixed(3).replace('.', ',') : '—'}</dd>
      <dt>TVA sur marge</dt><dd>${money(tva)}</dd><dt>Marge nette</dt><dd><b>${money(margin - tva)}</b></dd></dl>`;
    const pub = n(val(root, 'pub').value);
    const com = n(val(root, 'com').value);
    const tax = n(val(root, 'tax').value);
    const commission = (pub - tax) * com / 100;
    el.querySelector('#com-out').innerHTML = `<dl class="kv"><dt>Commission perçue</dt><dd><b>${money(commission)}</b></dd><dt>Net à payer au fournisseur</dt><dd>${money(pub - commission)}</dd>
      <dt>Équivalent taux de marque</dt><dd>${pct(pub ? (commission / pub) * 100 : 0)}</dd></dl>`;
  };

  const fx = () => {
    const root = el.querySelector('#fx');
    const conv = makeConverter(state.currencies, val(root, 'to').value, val(root, 'safety').checked ? s.securite_change : 0);
    const amt = n(val(root, 'amt').value);
    const r = conv(amt, val(root, 'from').value);
    const rate = conv(1, val(root, 'from').value);
    el.querySelector('#fx-out').innerHTML = `<p class="big">${money(amt, val(root, 'from').value)} = <b>${money(r, val(root, 'to').value)}</b></p><p class="muted small">1 ${esc(val(root, 'from').value)} = ${rate.toFixed(4).replace('.', ',')} ${esc(val(root, 'to').value)}</p>`;
  };

  const flight = () => {
    const root = el.querySelector('#fx');
    const a1 = airport(val(root, 'ap1').value);
    const a2 = airport(val(root, 'ap2').value);
    if (!a1 || !a2) {
      el.querySelector('#fl-out').innerHTML = '<p class="muted">Code IATA inconnu (saisissez un code à 3 lettres).</p>';
      return;
    }
    const d1 = val(root, 'd1').value;
    const d = durationBetween(d1, val(root, 't1').value, a1.tz, val(root, 'd2').value, val(root, 't2').value, a2.tz);
    const diff = tzDiffHours(a1.tz, a2.tz, d1);
    const utc = zonedToUtc(d1, val(root, 't1').value, a1.tz);
    el.querySelector('#fl-out').innerHTML = `<dl class="kv"><dt>${esc(a1.name)}</dt><dd>${esc(a1.tz)}</dd><dt>${esc(a2.name)}</dt><dd>${esc(a2.tz)}</dd>
      <dt>Décalage horaire</dt><dd>${diff > 0 ? '+' : ''}${String(diff).replace('.', ',')} h</dd>
      <dt>Durée réelle du vol</dt><dd><b>${d > 0 ? fmtDuration(d) : '<span class="neg">arrivée avant le départ : vérifiez les dates</span>'}</b></dd>
      <dt>Départ en heure de Paris</dt><dd>${new Date(utc).toLocaleString('fr-FR', { timeZone: 'Europe/Paris', dateStyle: 'short', timeStyle: 'short' })}</dd></dl>`;
  };

  const qc = () => {
    const conv = makeConverter(state.currencies, s.devise_base || 'EUR', 0);
    const rows = offers.map((o) => ({ ...o, eur: conv(n(o.prix), o.devise) + (o.bagage ? 0 : 0) }));
    const range = (k) => {
      const v = rows.map((r) => (k === 'eur' ? r.eur : n(r[k])));
      return [Math.min(...v), Math.max(...v)];
    };
    const norm = (v, [min, max], lowerBetter) => (max === min ? 1 : lowerBetter ? (max - v) / (max - min) : (v - min) / (max - min));
    const rP = range('eur'); const rD = range('duree'); const rE = range('escales'); const rC = range('confort');
    const wSum = Object.values(weights).reduce((a, b) => a + b, 0) || 1;
    rows.forEach((r) => {
      r.score = Math.round(100 * (weights.prix * norm(r.eur, rP, true) + weights.duree * norm(n(r.duree), rD, true)
        + weights.escales * norm(n(r.escales), rE, true) + weights.confort * norm(n(r.confort), rC, false)) / wSum);
    });
    const best = Math.max(...rows.map((r) => r.score));
    el.querySelector('#qc-table').innerHTML = `<table class="table qc"><thead><tr><th>Offre</th><th>Prix</th><th>Devise</th><th>Durée (h)</th><th>Escales</th><th>Bagage inclus</th><th>Confort (1-5)</th><th class="num">≈ ${esc(s.devise_base || 'EUR')}</th><th class="num">Score</th><th></th></tr></thead>
      <tbody>${rows.map((r, i) => `<tr data-i="${i}" class="${r.score === best ? 'best-row' : ''}">
        <td><input data-k="label" value="${esc(r.label)}"></td><td><input data-k="prix" inputmode="decimal" value="${esc(r.prix)}" class="w6"></td>
        <td><select data-k="devise">${state.currencies.map((c) => `<option ${c.code === r.devise ? 'selected' : ''}>${esc(c.code)}</option>`).join('')}</select></td>
        <td><input data-k="duree" inputmode="decimal" value="${esc(r.duree)}" class="w4"></td><td><input data-k="escales" type="number" min="0" value="${esc(r.escales)}" class="w4"></td>
        <td><input data-k="bagage" type="checkbox" ${r.bagage ? 'checked' : ''}></td><td><input data-k="confort" type="number" min="1" max="5" value="${esc(r.confort)}" class="w4"></td>
        <td class="num">${money(r.eur, null, false)}</td><td class="num"><b>${r.score}</b>${r.score === best ? ' 🏆' : ''}</td>
        <td><button class="icon-btn danger" data-qc="del">✕</button></td></tr>`).join('')}</tbody></table>
      <p class="muted small">Astuce : une offre sans bagage inclus coûte souvent 60 à 150 € de plus par personne aller-retour — ajoutez-le au prix pour comparer à égalité.</p>`;
  };

  calc(); fx(); flight(); qc();

  el.oninput = (e) => {
    if (e.target.closest('#calc')) calc();
    else if (e.target.closest('#fx') && ['ap1', 'ap2', 'd1', 't1', 'd2', 't2'].includes(e.target.name)) flight();
    else if (e.target.closest('#fx')) fx();
    else if (e.target.dataset.w) { weights[e.target.dataset.w] = +e.target.value; qc(); }
  };
  el.onchange = (e) => {
    if (e.target.closest('#calc')) calc();
    if (e.target.closest('#fx')) { fx(); flight(); }
    const tr = e.target.closest('#qc tr[data-i]');
    if (tr && e.target.dataset.k) {
      const k = e.target.dataset.k;
      offers[+tr.dataset.i][k] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
      saveOffers(offers);
      qc();
    }
  };
  el.onclick = (e) => {
    const b = e.target.closest('[data-qc]');
    if (!b) return;
    if (b.dataset.qc === 'add') offers.push({ label: `Offre ${String.fromCharCode(65 + offers.length)}`, prix: 0, devise: s.devise_base || 'EUR', duree: 0, escales: 0, bagage: true, confort: 3 });
    if (b.dataset.qc === 'del') offers.splice(+b.closest('tr').dataset.i, 1);
    if (b.dataset.qc === 'reset') offers = [{ label: 'Offre A', prix: 0, devise: s.devise_base || 'EUR', duree: 0, escales: 0, bagage: true, confort: 3 }];
    if (!offers.length) offers.push({ label: 'Offre A', prix: 0, devise: 'EUR', duree: 0, escales: 0, bagage: true, confort: 3 });
    saveOffers(offers);
    qc();
  };
}
