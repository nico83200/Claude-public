// Moteur de suggestions « anti-oubli » — règles métier déterministes (instantanées, gratuites, hors ligne).
// Chaque suggestion : { level: 'important'|'conseil'|'info', title, detail, add?: { type, ...préremplissage } }

import { addDays, daysBetween, itemEndUtc, itemStartUtc, todayIso } from './time.js';
import { airport } from './airports.js';

const TRANSPORT = ['vol', 'train', 'croisiere'];
const fmt = (d) => (d ? d.split('-').reverse().slice(0, 2).join('/') : '');

export function runChecks({ trip = {}, option = {}, destination = null, travelers = [], client = null, computed = null, today = todayIso() }) {
  const out = [];
  const add = (level, title, detail = '', addItem = null) => out.push({ level, title, detail, add: addItem });
  const all = (option.items || []).filter((i) => i.statut !== 'annule');
  const items = all.filter((i) => !+i.optionnel);
  const byType = (t) => items.filter((i) => i.type === t);
  const sorted = [...items].sort((a, b) => (a.date_debut || '9999').localeCompare(b.date_debut || '9999') || (a.heure_debut || '').localeCompare(b.heure_debut || ''));
  const start = trip.date_depart;
  const end = trip.date_retour;
  const horsUE = !!+destination?.hors_ue;

  if (!items.length) {
    add('info', 'Variante vide', 'Ajoutez des prestations, ou importez une capture d\'écran / un PDF de réservation avec l\'assistant IA.');
    return out;
  }

  // 1. Transport aller / retour
  const transports = sorted.filter((i) => TRANSPORT.includes(i.type));
  if (start && !transports.some((i) => i.date_debut && Math.abs(daysBetween(start, i.date_debut)) <= 1)) {
    add('important', 'Transport aller manquant', `Aucun vol / train / départ de croisière autour du ${fmt(start)}.`, { type: 'vol', date_debut: start, lieu_depart: trip.ville_depart || '' });
  }
  if (end && start !== end && !transports.some((i) => (i.date_fin || i.date_debut) && Math.abs(daysBetween(i.date_fin || i.date_debut, end)) <= 1)) {
    add('important', 'Transport retour manquant', `Aucun retour prévu autour du ${fmt(end)}.`, { type: 'vol', date_debut: end, lieu_arrivee: trip.ville_depart || '' });
  }

  // 2. Nuits non couvertes
  if (start && end && daysBetween(start, end) > 0) {
    const stays = items.filter((i) => ['hebergement', 'croisiere'].includes(i.type) && i.date_debut);
    const overnightTransport = items.filter((i) => ['vol', 'train'].includes(i.type) && i.date_debut && i.date_fin && i.date_fin > i.date_debut);
    const uncovered = [];
    for (let d = start; d < end; d = addDays(d, 1)) {
      const covered = stays.some((s) => s.date_debut <= d && d < (s.date_fin || addDays(s.date_debut, 1)))
        || overnightTransport.some((f) => f.date_debut <= d && d < f.date_fin);
      if (!covered) uncovered.push(d);
    }
    if (uncovered.length) {
      add('important', `${uncovered.length} nuit${uncovered.length > 1 ? 's' : ''} sans hébergement`,
        uncovered.map(fmt).join(', ') + (uncovered.length > 6 ? '' : ''),
        { type: 'hebergement', date_debut: uncovered[0], date_fin: addDays(uncovered[uncovered.length - 1], 1), unite: 'chambre_nuit' });
    }
  }
  for (const h of byType('hebergement')) {
    if (h.date_debut && !h.date_fin) add('conseil', `Date de départ manquante : ${h.libelle}`, 'Indiquez la date de fin pour calculer les nuits et vérifier la couverture.');
  }

  // 3. Transferts aux arrivées / départs
  const flights = sorted.filter((i) => i.type === 'vol');
  const ground = items.filter((i) => ['transfert', 'location'].includes(i.type));
  const hasGroundOn = (date) => ground.some((g) => g.date_debut === date || (g.type === 'location' && g.date_debut <= date && date <= (g.date_fin || g.date_debut)));
  flights.forEach((f, idx) => {
    const arr = f.date_fin || f.date_debut;
    if (idx < flights.length - 1 && arr && !hasGroundOn(arr)) {
      const ap = airport(f.lieu_arrivee);
      add('conseil', `Transfert à l'arrivée ${f.lieu_arrivee ? 'à ' + (ap?.name || f.lieu_arrivee) : ''} (${fmt(arr)})`,
        `${f.libelle}${f.heure_fin ? ' — arrivée ' + f.heure_fin : ''}. Aucun transfert ni location prévu ce jour-là.`,
        { type: 'transfert', date_debut: arr, lieu_depart: f.lieu_arrivee || '', libelle: `Transfert aéroport ${f.lieu_arrivee || ''} → hôtel`.trim(), unite: 'vehicule', mode_transfert: 'prive' });
    }
    if (idx > 0 && f.date_debut && !hasGroundOn(f.date_debut)) {
      add('conseil', `Transfert vers l'aéroport ${f.lieu_depart || ''} (${fmt(f.date_debut)})`.replace('  ', ' '),
        `${f.libelle}${f.heure_debut ? ' — départ ' + f.heure_debut : ''}.`,
        { type: 'transfert', date_debut: f.date_debut, lieu_arrivee: f.lieu_depart || '', libelle: `Transfert hôtel → aéroport ${f.lieu_depart || ''}`.trim(), unite: 'vehicule', mode_transfert: 'prive' });
    }
  });
  if (flights.length && !items.some((i) => i.type === 'train' && i.date_debut === flights[0].date_debut) && trip.ville_depart
      && !airport(trip.ville_depart) && !/paris/i.test(trip.ville_depart)) {
    add('info', 'Pré-acheminement', `Départ de ${trip.ville_depart} : prévoir train, parking ou nuit à l'aéroport avant le premier vol ?`, { type: 'train', date_debut: flights[0].date_debut });
  }

  // 4. Correspondances
  for (let i = 0; i < transports.length - 1; i++) {
    const a = transports[i];
    const b = transports[i + 1];
    if (!a.heure_fin || !b.heure_debut) continue;
    const gap = (itemStartUtc(b) - itemEndUtc(a)) / 60000;
    if (isNaN(gap)) continue;
    if (gap < 0 && a.type !== 'croisiere') add('important', 'Horaires incohérents', `${b.libelle} part avant l'arrivée de ${a.libelle}.`);
    else if (gap >= 0 && gap < 120 && a.type === 'vol' && b.type === 'vol' && a.ref_reservation !== b.ref_reservation) {
      add('important', `Correspondance serrée (${Math.round(gap)} min)`, `${a.libelle} → ${b.libelle} sur billets séparés : risque de vol manqué non couvert.`);
    }
  }

  // 5. Croisière : nuit pré-embarquement
  for (const c of byType('croisiere')) {
    const sameDayFlight = flights.find((f) => (f.date_fin || f.date_debut) === c.date_debut);
    if (sameDayFlight) {
      add('conseil', 'Arrivée le jour de l\'embarquement', `Le vol ${sameDayFlight.numero || ''} arrive le jour du départ de la croisière : en cas de retard le navire n'attend pas. Conseillez une nuit pré-croisière.`,
        { type: 'hebergement', date_debut: addDays(c.date_debut, -1), date_fin: c.date_debut, unite: 'chambre_nuit' });
    }
    if (!+c.taxes) add('info', `Taxes portuaires : ${c.libelle}`, 'Vérifiez que les taxes portuaires et le forfait de séjour à bord (pourboires) sont inclus ou mentionnés en « non inclus ».');
  }

  // 6. Arrivées tardives / matinales
  for (const f of flights) {
    const arr = f.date_fin || f.date_debut;
    const hotel = byType('hebergement').find((h) => h.date_debut === arr);
    if (!hotel || !f.heure_fin) continue;
    if (f.heure_fin >= '22:00' || f.heure_fin < '05:00') add('info', 'Arrivée tardive à l\'hôtel', `Prévenir ${hotel.libelle} d'une arrivée vers ${f.heure_fin} (garantie de la chambre).`);
    else if (f.heure_fin < '11:00') add('conseil', 'Arrivée matinale', `Arrivée à ${f.heure_fin} : proposer un early check-in ou une nuit supplémentaire pour une chambre disponible dès l'arrivée.`);
  }

  // 7. Assurance (devoir de conseil)
  if (!all.some((i) => i.type === 'assurance')) {
    add('important', 'Assurance non proposée', 'Proposez une assurance annulation / multirisque (assistance rapatriement indispensable hors UE). En cas de refus, faites-le acter par écrit.',
      { type: 'assurance', libelle: 'Assurance multirisque', unite: 'personne' });
  }

  // 8. Formalités
  if (horsUE) {
    const f = (destination?.formalites || '').toLowerCase();
    if (/visa|esta|eta\b|e-visa|evisa|autorisation/.test(f) && !all.some((i) => i.type === 'visa')) {
      add('important', 'Formalités d\'entrée', destination.formalites, { type: 'visa', libelle: 'Visa / autorisation de voyage', unite: 'personne' });
    }
  }
  const people = [...travelers.map((t) => t.client).filter(Boolean)];
  if (client && !people.some((p) => p.id === client.id)) people.push(client);
  const limit = end ? addDays(end, 182) : null;
  for (const p of people) {
    const name = `${p.prenom || ''} ${p.nom}`.trim();
    if (horsUE && limit) {
      if (!p.passeport_expiration) add('conseil', `Passeport de ${name}`, 'Date d\'expiration non renseignée.');
      else if (p.passeport_expiration < limit) add('important', `Passeport de ${name}`, `Expire le ${p.passeport_expiration.split('-').reverse().join('/')} : de nombreux pays exigent 6 mois de validité après le retour.`);
    } else if (!horsUE && start && p.cni_expiration && p.cni_expiration < start && p.passeport_expiration && p.passeport_expiration < start) {
      add('important', `Pièce d'identité de ${name}`, 'CNI et passeport expirés à la date de départ.');
    }
  }
  const nbPax = (+trip.nb_adultes || 0) + (+trip.nb_enfants || 0) + (+trip.nb_bebes || 0);
  if (travelers.length < nbPax) add('info', 'Voyageurs à renseigner', `${travelers.length}/${nbPax} voyageurs nommés (noms exacts du passeport requis pour l'émission des billets).`);
  if (+trip.nb_bebes > 0 && flights.length) add('info', 'Bébé à bord', 'Vérifiez le tarif bébé, le berceau (bassinet) et la poussette en soute auprès de la compagnie.');

  // 9. Location de voiture
  const car = byType('location')[0];
  if (car && horsUE) add('info', 'Permis de conduire', 'Permis international potentiellement requis : vérifiez pour la destination. Carte de crédit au nom du conducteur pour la caution.');

  // 10. Cohérence des dates
  for (const i of items) {
    if (start && i.date_debut && i.date_debut < addDays(start, -1)) add('conseil', `Date antérieure au départ : ${i.libelle}`, `Prévue le ${fmt(i.date_debut)}, départ le ${fmt(start)}.`);
    else if (end && i.date_debut && i.date_debut > end) add('conseil', `Date postérieure au retour : ${i.libelle}`, `Prévue le ${fmt(i.date_debut)}, retour le ${fmt(end)}.`);
    if (i.date_debut && i.date_fin && i.date_fin < i.date_debut) add('important', `Dates inversées : ${i.libelle}`);
  }

  // 11. Prix
  for (const i of items) {
    if (!+i.prix_unitaire && !+i.taxes && (i.prix_vente_force == null || i.prix_vente_force === '')) add('conseil', `Prix manquant : ${i.libelle}`, 'Le total du devis est incomplet.');
  }
  if (computed) {
    const t = computed.totals;
    if (computed.missingRates?.length) add('important', 'Taux de change manquant', `Ajoutez le taux ${computed.missingRates.join(', ')} dans Réglages > Devises (montants pris à 1:1).`);
    if (t.marge < 0) add('important', 'Marge négative', `Vous perdez ${Math.abs(t.marge).toFixed(2)} € sur cette variante.`);
    else if (t.vente > 0 && t.tauxMarque < 5) add('conseil', 'Marge faible', `Taux de marque ${t.tauxMarque.toFixed(1)} % : pensez aux frais de dossier ou à une majoration.`);
    if (t.budgetEcart != null && t.budgetEcart > (+trip.budget || 0) * 0.1) add('info', 'Budget dépassé', `${t.budgetEcart.toFixed(0)} € au-dessus du budget client : préparez une variante plus économique.`);
  }

  // 12. Options fournisseurs
  for (const i of items) {
    if (i.statut === 'option' && i.date_limite_option) {
      const d = daysBetween(today, i.date_limite_option);
      if (d < 0) add('important', `Option expirée : ${i.libelle}`, `Depuis le ${fmt(i.date_limite_option)}.`);
      else if (d <= 3) add('important', `Option à confirmer : ${i.libelle}`, `Expire le ${fmt(i.date_limite_option)} (J-${d}).`);
    }
  }

  // 13. Ventes additionnelles & contenu
  const nights = start && end ? daysBetween(start, end) : 0;
  if (nights >= 5 && !all.some((i) => i.type === 'activite')) add('info', 'Excursions', 'Aucune activité proposée : suggérez 1 ou 2 excursions phares (en option si besoin).', { type: 'activite', optionnel: 1, unite: 'personne' });
  const longHaul = flights.filter((f) => (f.duree_min || 0) >= 360 && !/business|premium|first|affaires/i.test(f.classe || ''));
  if (longHaul.length) add('info', 'Confort long-courrier', 'Proposez la Premium Economy, un siège réservé ou l\'accès salon sur les vols de plus de 6 h.');
  if (!(option.days || []).length && nights > 0) add('info', 'Programme jour par jour', 'Générez le programme depuis les prestations pour enrichir le devis.');

  const rank = { important: 0, conseil: 1, info: 2 };
  return out.sort((a, b) => rank[a.level] - rank[b.level]);
}

/** Génère un programme jour par jour à partir des prestations. */
export function generateDays(trip, option) {
  const items = (option.items || []).filter((i) => i.statut !== 'annule' && !+i.optionnel);
  const dates = items.flatMap((i) => [i.date_debut, i.date_fin]).filter(Boolean).sort();
  const start = trip.date_depart || dates[0];
  const end = trip.date_retour || dates[dates.length - 1];
  if (!start || !end) return [];
  const days = [];
  let n = 1;
  for (let d = start; d <= end && n <= 60; d = addDays(d, 1), n++) {
    const todays = items.filter((i) => i.date_debut === d);
    const stay = items.find((i) => ['hebergement', 'croisiere'].includes(i.type) && i.date_debut <= d && d < (i.date_fin || i.date_debut));
    const moves = todays.filter((i) => ['vol', 'train'].includes(i.type));
    let titre;
    if (moves.length) {
      const name = (code) => airport(code)?.name.replace(/ (Charles-de-Gaulle|Orly|Suvarnabhumi|El Prat|Barajas|Schiphol|Heathrow|Gatwick|Changi)$/, '') || code || '';
      titre = `${name(moves[0].lieu_depart)} ✈ ${name(moves[moves.length - 1].lieu_arrivee)}`.trim();
    } else if (stay?.type === 'croisiere') {
      titre = d === stay.date_debut ? 'Embarquement' : 'Croisière';
    } else {
      titre = todays.find((i) => i.type === 'activite')?.libelle || (stay ? stay.libelle.replace(/^(Hôtel|Hotel)\s+/i, '') : 'Journée libre');
    }
    const desc = todays.map((i) => i.libelle + (i.heure_debut ? ` (${i.heure_debut})` : '')).join(' · ');
    days.push({ jour: n, titre, lieu: stay ? (stay.type === 'croisiere' ? 'À bord' : stay.libelle) : '', description: desc, repas: stay?.pension || '' });
  }
  return days;
}
