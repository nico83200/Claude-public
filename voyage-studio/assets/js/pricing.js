// Moteur de tarification — module pur (aucun accès DOM/réseau), testé dans tests/pricing.test.mjs.
//
// Principes métier :
//  - Prestation en « net » : l'agent applique sa marge (majoration, taux de marque ou montant fixe).
//  - Prestation « commissionnée » : vendue au prix public, l'agent perçoit une commission ;
//    les taxes (aéroport, portuaires, séjour) ne sont jamais commissionnables ni majorées.
//  - Achats en devises : conversion au taux du jour + coussin de sécurité de change paramétrable.
//  - TVA sur la marge (régime particulier des agences de voyages, art. 266-1-e CGI) :
//    TVA = marge TTC × taux / (100 + taux), exonérée pour les voyages hors UE ; nulle en franchise de TVA.

import { daysBetween, itemDuration } from './time.js';

const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const num = (v) => (v === null || v === undefined || v === '' || isNaN(+v) ? 0 : +v);

export function payingPax(trip) {
  return Math.max(1, num(trip?.nb_adultes) + num(trip?.nb_enfants));
}

export function itemNights(item) {
  const n = daysBetween(item.date_debut, item.date_fin);
  return n && n > 0 ? n : 1;
}

/** Quantité par défaut selon l'unité de tarif. */
export function defaultQty(unit, trip) {
  const pax = payingPax(trip);
  switch (unit) {
    case 'personne':
    case 'personne_nuit':
      return pax;
    case 'chambre':
    case 'chambre_nuit':
      return Math.max(1, Math.ceil(pax / 2));
    default:
      return 1;
  }
}

export function unitMultiplier(unit, item) {
  return ['nuit', 'personne_nuit', 'chambre_nuit', 'jour'].includes(unit) ? itemNights(item) : 1;
}

/** Fabrique un convertisseur vers la devise de base. */
export function makeConverter(rates, base = 'EUR', safetyPct = 0) {
  const map = {};
  for (const c of rates || []) map[c.code] = +c.taux;
  map.EUR = map.EUR || 1;
  const missing = new Set();
  const fn = (amount, cur) => {
    cur = (cur || base).toUpperCase();
    if (cur === base) return amount;
    const from = map[cur];
    const to = map[base] || 1;
    if (!from) {
      missing.add(cur);
      return amount;
    }
    return (amount / from) * to * (1 + num(safetyPct) / 100);
  };
  fn.missing = missing;
  fn.rate = (cur) => (map[cur] && map[base] ? map[base] / map[cur] : null);
  return fn;
}

/**
 * Calcule une variante.
 * @param {object} option  variante avec option.items
 * @param {object} ctx     { trip, settings, currencies, horsUE }
 */
export function computeOption(option, ctx) {
  const { trip = {}, settings = {}, currencies = [], horsUE = false } = ctx;
  const base = settings.devise_base || 'EUR';
  const conv = makeConverter(currencies, base, settings.securite_change);
  const pax = payingPax(trip);
  const items = (option.items || []).filter((i) => i.statut !== 'annule');

  const lines = items.map((item) => {
    const unit = item.unite || 'forfait';
    const qty = item.quantite != null && item.quantite !== '' && +item.quantite > 0 ? +item.quantite : defaultQty(unit, trip);
    const mult = unitMultiplier(unit, item);
    const gross = num(item.prix_unitaire) * qty * mult; // en devise de la prestation
    const taxes = num(item.taxes);
    const cur = item.devise || base;
    let commission = 0;
    let cost;
    let sell = null;
    let markupBase = 0;
    if (item.mode_tarif === 'commission') {
      commission = gross * num(item.commission_pct) / 100;
      cost = conv(gross - commission + taxes, cur);
      sell = conv(gross + taxes, cur);
    } else {
      markupBase = conv(gross, cur);
      cost = markupBase + conv(taxes, cur);
    }
    return {
      item, unit, qty, mult, gross, cur, taxes,
      taxesBase: conv(taxes, cur),
      commission: conv(commission, cur),
      cost, sell, markupBase,
      optional: !!+item.optionnel,
      forced: item.prix_vente_force != null && item.prix_vente_force !== '',
      override: item.marge_pct != null && item.marge_pct !== '',
    };
  });

  // Marge sur les prestations nettes
  const mode = option.marge_mode || 'coef';
  const v = num(option.marge_valeur);
  const applyPct = (l, pct, kind) => {
    if (kind === 'marque') return pct >= 100 ? l.markupBase : l.markupBase / (1 - pct / 100);
    return l.markupBase * (1 + pct / 100);
  };
  const netLines = lines.filter((l) => l.item.mode_tarif !== 'commission');
  for (const l of netLines) {
    if (l.override) l.sell = applyPct(l, num(l.item.marge_pct), 'coef') + l.taxesBase;
    else if (mode === 'coef' || mode === 'marque') l.sell = applyPct(l, v, mode) + l.taxesBase;
    else l.sell = l.markupBase + l.taxesBase;
  }
  let undistributedFixed = 0;
  if (mode === 'fixe_pers' || mode === 'fixe_total') {
    const fixed = mode === 'fixe_pers' ? v * pax : v;
    const targets = netLines.filter((l) => !l.override && !l.forced && !l.optional);
    const totalBase = targets.reduce((s, l) => s + l.markupBase, 0);
    if (totalBase > 0) {
      for (const l of targets) l.sell += fixed * (l.markupBase / totalBase);
    } else {
      undistributedFixed = fixed;
    }
  }
  for (const l of lines) {
    if (l.forced) l.sell = num(l.item.prix_vente_force);
    l.cost = r2(l.cost);
    l.sell = r2(l.sell);
    l.margin = r2(l.sell - l.cost);
  }

  const included = lines.filter((l) => !l.optional);
  const optional = lines.filter((l) => l.optional);
  const sum = (arr, k) => arr.reduce((s, l) => s + l[k], 0);

  const achat = sum(included, 'cost');
  let vente = sum(included, 'sell');
  const fees = num(option.frais_dossier) + num(option.frais_dossier_pers) * pax + undistributedFixed;
  vente += fees;

  // Arrondi commercial du prix par personne (au multiple supérieur)
  let arrondi = 0;
  const step = num(option.arrondi);
  if (step > 0 && vente > 0) {
    const pp = vente / pax;
    const ppR = Math.ceil(pp / step - 1e-9) * step;
    arrondi = ppR * pax - vente;
    vente += arrondi;
  }

  const marge = vente - achat;
  const tvaTaux = num(settings.tva_taux);
  const tva = settings.tva_regime === 'marge' && !horsUE && marge > 0 ? (marge * tvaTaux) / (100 + tvaTaux) : 0;

  const byType = {};
  for (const l of included) {
    const t = l.item.type;
    byType[t] = byType[t] || { achat: 0, vente: 0, count: 0 };
    byType[t].achat += l.cost;
    byType[t].vente += l.sell;
    byType[t].count++;
  }

  const stats = optionStats(option, trip);
  const totals = {
    achat: r2(achat),
    vente: r2(vente),
    fees: r2(fees),
    arrondi: r2(arrondi),
    commissions: r2(sum(included, 'commission')),
    taxes: r2(sum(included, 'taxesBase')),
    marge: r2(marge),
    tva: r2(tva),
    margeNette: r2(marge - tva),
    tauxMarque: vente > 0 ? (marge / vente) * 100 : 0,
    coef: achat > 0 ? vente / achat : 0,
    pax,
    parPers: r2(vente / pax),
    parPersNuit: stats.nights > 0 ? r2(vente / pax / stats.nights) : null,
    optionnel: r2(sum(optional, 'sell')),
    budgetEcart: trip.budget ? r2(vente - num(trip.budget)) : null,
  };
  return { lines, included, optional, totals, byType, stats, base, missingRates: [...conv.missing] };
}

/** Indicateurs de durée / confort d'une variante. */
export function optionStats(option, trip = {}) {
  const items = (option.items || []).filter((i) => i.statut !== 'annule' && !+i.optionnel);
  const flights = items.filter((i) => i.type === 'vol');
  const transfers = items.filter((i) => i.type === 'transfert');
  const stays = items.filter((i) => i.type === 'hebergement' || i.type === 'croisiere');
  const dur = (arr) => arr.reduce((s, i) => s + (itemDuration(i) || 0), 0);

  const dates = items.flatMap((i) => [i.date_debut, i.date_fin]).filter(Boolean).sort();
  const start = trip.date_depart || dates[0] || null;
  const end = trip.date_retour || dates[dates.length - 1] || null;
  const tripNights = start && end ? Math.max(0, daysBetween(start, end)) : 0;
  const stayNights = stays.reduce((s, i) => s + (i.date_debut && i.date_fin ? Math.max(0, daysBetween(i.date_debut, i.date_fin)) : 0), 0);

  return {
    start, end,
    days: start && end ? tripNights + 1 : null,
    nights: tripNights || stayNights,
    stayNights,
    flights: flights.length,
    flightMin: dur(flights),
    stops: flights.reduce((s, i) => s + num(i.escales), 0),
    maxStops: flights.reduce((m, i) => Math.max(m, num(i.escales)), 0),
    transfers: transfers.length,
    transferMin: dur(transfers),
    travelMin: dur([...flights, ...transfers, ...items.filter((i) => i.type === 'train')]),
    boards: [...new Set(stays.map((i) => i.pension).filter(Boolean))],
    categories: [...new Set(stays.map((i) => i.categorie).filter(Boolean))],
    toBook: items.filter((i) => ['a_demander', 'demande', 'option'].includes(i.statut)).length,
  };
}

/** Prix de vente à partir d'un coût (outil calculatrice). */
export function sellFromCost(cost, mode, value) {
  cost = num(cost);
  value = num(value);
  switch (mode) {
    case 'marque': return value >= 100 ? cost : cost / (1 - value / 100);
    case 'fixe': return cost + value;
    default: return cost * (1 + value / 100);
  }
}

export function vatOnMargin(margin, rate) {
  return margin > 0 ? (margin * num(rate)) / (100 + num(rate)) : 0;
}

/** Échéancier client : acompte à la réservation + solde à J-x (tout à la réservation si départ proche). */
export function paymentSchedule(total, { acomptePct = 30, soldeJours = 30, depart = null, today }) {
  total = r2(num(total));
  if (!total) return [];
  const soldeDate = depart ? new Date(Date.parse(depart + 'T00:00:00Z') - soldeJours * 86400000).toISOString().slice(0, 10) : null;
  if (!soldeDate || soldeDate <= today) {
    return [{ libelle: 'Règlement total à la réservation', montant: total, date_echeance: today }];
  }
  const acompte = r2(total * num(acomptePct) / 100);
  return [
    { libelle: `Acompte ${num(acomptePct)} % à la réservation`, montant: acompte, date_echeance: today },
    { libelle: `Solde (J-${soldeJours})`, montant: r2(total - acompte), date_echeance: soldeDate },
  ];
}
