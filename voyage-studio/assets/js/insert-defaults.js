// Valeurs par défaut d'une prestation insérée entre deux éléments de la frise (module pur, testé).

import { addDays, daysBetween } from './time.js';
import { airport } from './airports.js';

const STAYS = ['hebergement', 'croisiere'];
const MOVES = ['vol', 'train', 'transfert'];

export function addMinutes(hhmm, minutes) {
  if (!hhmm) return '';
  const [h, m] = hhmm.split(':').map(Number);
  const t = Math.max(0, Math.min(23 * 60 + 59, h * 60 + m + minutes));
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

/** Point de sortie d'une prestation : quand et où se trouve le voyageur après elle. */
export function endPoint(i) {
  if (!i) return {};
  if (STAYS.includes(i.type) || i.type === 'location') {
    return { date: i.date_fin || i.date_debut, time: null, place: i.type === 'hebergement' ? stayName(i) : i.lieu_arrivee || null };
  }
  return { date: i.date_fin || i.date_debut, time: i.heure_fin || null, place: i.lieu_arrivee || null };
}

/** Point d'entrée d'une prestation : quand et où le voyageur doit être pour la commencer. */
export function startPoint(i) {
  if (!i) return {};
  return { date: i.date_debut, time: i.heure_debut || null, place: i.type === 'hebergement' ? stayName(i) : i.lieu_depart || null };
}

function stayName(i) {
  return (i.libelle || 'Hôtel').replace(/\s+—.*$/, '').trim();
}

const DEFAULT_LABELS = {
  activite: 'Excursion', assurance: 'Assurance voyage', visa: 'Visa / formalités', frais: 'Frais de dossier', autre: 'Prestation',
};

const placeLabel = (p) => {
  if (!p) return '';
  const a = /^[A-Z]{3}$/.test(p) ? airport(p) : null;
  return a ? `${a.name.split(' ')[0]} ${a.code}` : p;
};

/** Libellé lisible généré à partir des champs saisis. */
export function autoLabel(v, itemTypes = {}) {
  const from = placeLabel(v.lieu_depart);
  const to = placeLabel(v.lieu_arrivee);
  const route = from || to ? `${from || '…'} → ${to || '…'}` : '';
  switch (v.type) {
    case 'vol': return [`Vol ${route}`.trim(), [v.compagnie, v.numero].filter(Boolean).join(' ')].filter(Boolean).join(' — ');
    case 'train': return [`Train ${route}`.trim(), v.numero].filter(Boolean).join(' — ');
    case 'transfert': return `Transfert ${route}`.trim();
    case 'hebergement': {
      const n = v.date_debut && v.date_fin ? daysBetween(v.date_debut, v.date_fin) : 0;
      return `Hôtel${n > 0 ? ` — ${n} nuit${n > 1 ? 's' : ''}` : ''}`;
    }
    case 'croisiere': return `Croisière${v.navire ? ` ${v.navire}` : ''}${v.date_debut && v.date_fin ? ` — ${daysBetween(v.date_debut, v.date_fin)} nuits` : ''}`;
    case 'location': return `Location de voiture${from ? ` — ${from}` : ''}`;
    default: return itemTypes[v.type] || DEFAULT_LABELS[v.type] || 'Prestation';
  }
}

/**
 * Pré-remplissage d'une prestation de type `type` insérée entre `prev` et `next`
 * (l'un ou l'autre peut être absent : début ou fin de frise).
 */
export function defaultsBetween(prev, next, type, trip = {}) {
  const a = endPoint(prev);
  const b = startPoint(next);
  const v = { type };
  v.date_debut = a.date || b.date || trip.date_depart || '';

  if (STAYS.includes(type) || type === 'location') {
    const end = b.date && v.date_debut && b.date > v.date_debut ? b.date
      : !prev && !next && trip.date_retour ? trip.date_retour
      : v.date_debut ? addDays(v.date_debut, 1) : '';
    v.date_fin = end;
    if (type === 'location') {
      if (a.place) v.lieu_depart = a.place;
      if (b.place) v.lieu_arrivee = b.place;
    }
  } else if (MOVES.includes(type)) {
    v.date_fin = v.date_debut;
    if (a.place) v.lieu_depart = a.place;
    if (b.place && b.place !== a.place) v.lieu_arrivee = b.place;
    // horaires : après l'arrivée précédente, ou suffisamment avant le départ suivant
    if (type === 'transfert') {
      if (a.time && a.date === v.date_debut) v.heure_debut = addMinutes(a.time, 30);
      else if (b.time && b.date === v.date_debut) v.heure_debut = addMinutes(b.time, next?.type === 'vol' ? -180 : -60);
    } else if (a.time && a.date === v.date_debut) {
      v.heure_debut = addMinutes(a.time, type === 'vol' ? 120 : 60);
    }
    // un vol part d'un aéroport : ne reprend pas un nom d'hôtel comme lieu de départ
    if (type === 'vol') {
      if (v.lieu_depart && !/^[A-Z]{3}$/.test(v.lieu_depart)) delete v.lieu_depart;
      if (v.lieu_arrivee && !/^[A-Z]{3}$/.test(v.lieu_arrivee)) delete v.lieu_arrivee;
      if (!prev && trip.ville_depart && airport(trip.ville_depart)) v.lieu_depart = airport(trip.ville_depart).code;
    }
  } else if (type === 'activite' && v.date_debut && prev && STAYS.includes(prev.type) && prev.date_debut) {
    v.date_debut = addDays(prev.date_debut, 1) < (prev.date_fin || '9999') ? addDays(prev.date_debut, 1) : prev.date_debut;
  }
  const ap = (p) => (p && /^[A-Z]{3}$/.test(p) ? airport(p) : null);
  if (ap(v.lieu_depart)) v.tz_depart = ap(v.lieu_depart).tz;
  if (ap(v.lieu_arrivee)) v.tz_arrivee = ap(v.lieu_arrivee).tz;
  v.libelle = autoLabel(v);
  return v;
}

/** Trajet retour (lieux inversés, date de retour du voyage pour un vol/train). */
export function returnOf(item, trip = {}) {
  const v = {
    type: item.type, supplier_id: item.supplier_id, compagnie: item.compagnie, classe: item.classe, bagages: item.bagages,
    unite: item.unite, quantite: item.quantite, devise: item.devise, mode_tarif: item.mode_tarif, commission_pct: item.commission_pct,
    mode_transfert: item.mode_transfert, categorie: item.categorie,
    lieu_depart: item.lieu_arrivee, lieu_arrivee: item.lieu_depart, tz_depart: item.tz_arrivee, tz_arrivee: item.tz_depart,
  };
  v.date_debut = ['vol', 'train'].includes(item.type) && trip.date_retour ? trip.date_retour : item.date_debut;
  v.date_fin = v.date_debut;
  v.libelle = autoLabel(v);
  return v;
}
