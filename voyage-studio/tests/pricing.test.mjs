// Lancer : node --test tests/
import test from 'node:test';
import assert from 'node:assert/strict';
import { computeOption, paymentSchedule, sellFromCost, vatOnMargin, defaultQty } from '../assets/js/pricing.js';
import { durationBetween, itemDuration, fmtDuration, zonedToUtc, sortChrono } from '../assets/js/time.js';
import { runChecks, generateDays, uncoveredRanges } from '../assets/js/checks.js';
import { hotelsComUrl, splitRooms, parseAges } from '../assets/js/search-links.js';

const settings = { devise_base: 'EUR', tva_regime: 'marge', tva_taux: '20', securite_change: '0' };
const currencies = [{ code: 'EUR', taux: 1 }, { code: 'USD', taux: 1.25 }];
const trip = { nb_adultes: 2, nb_enfants: 0, date_depart: '2027-06-13', date_retour: '2027-06-21' };

test('prestation nette avec majoration et taxes non majorées', () => {
  const opt = { marge_mode: 'coef', marge_valeur: 10, items: [{ type: 'vol', unite: 'personne', prix_unitaire: 100, taxes: 50 }] };
  const r = computeOption(opt, { trip, settings, currencies });
  assert.equal(r.totals.achat, 250);
  assert.equal(r.totals.vente, 270); // 200 × 1,10 + 50
  assert.equal(r.totals.marge, 20);
  assert.equal(r.totals.parPers, 135);
});

test('taux de marque', () => {
  const opt = { marge_mode: 'marque', marge_valeur: 20, items: [{ type: 'hebergement', unite: 'forfait', prix_unitaire: 800 }] };
  const r = computeOption(opt, { trip, settings, currencies });
  assert.equal(r.totals.vente, 1000);
  assert.ok(Math.abs(r.totals.tauxMarque - 20) < 1e-9);
});

test('prestation commissionnée : taxes portuaires non commissionnables', () => {
  const opt = { marge_mode: 'coef', marge_valeur: 50, items: [{ type: 'croisiere', mode_tarif: 'commission', commission_pct: 12, unite: 'personne', prix_unitaire: 1000, taxes: 300 }] };
  const r = computeOption(opt, { trip, settings, currencies });
  assert.equal(r.totals.vente, 2300); // prix public, la majoration ne s'applique pas
  assert.equal(r.totals.achat, 2060); // 2000 - 12 % + 300
  assert.equal(r.totals.marge, 240);
  assert.equal(r.totals.commissions, 240);
});

test('nuits, chambres et devises', () => {
  const opt = { marge_mode: 'coef', marge_valeur: 0, items: [{ type: 'hebergement', unite: 'chambre_nuit', prix_unitaire: 125, devise: 'USD', date_debut: '2027-06-13', date_fin: '2027-06-16' }] };
  const r = computeOption(opt, { trip, settings, currencies });
  assert.equal(r.lines[0].qty, 1);
  assert.equal(r.lines[0].mult, 3);
  assert.equal(r.totals.achat, 300); // 375 USD / 1,25
  const r2 = computeOption(opt, { trip, settings: { ...settings, securite_change: '2' }, currencies });
  assert.equal(r2.totals.achat, 306);
  const r3 = computeOption({ ...opt, items: [{ ...opt.items[0], devise: 'XYZ' }] }, { trip, settings, currencies });
  assert.deepEqual(r3.missingRates, ['XYZ']);
});

test('marge fixe par personne répartie + frais + arrondi', () => {
  const opt = {
    marge_mode: 'fixe_pers', marge_valeur: 50, frais_dossier: 20, arrondi: 10,
    items: [
      { type: 'vol', unite: 'personne', prix_unitaire: 300 },
      { type: 'hebergement', unite: 'forfait', prix_unitaire: 400 },
    ],
  };
  const r = computeOption(opt, { trip, settings, currencies });
  // achat 1000 + 100 marge + 20 frais = 1120 → 560/pers, déjà multiple de 10
  assert.equal(r.totals.vente, 1120);
  assert.equal(r.lines[0].sell, 660);
  const r2 = computeOption({ ...opt, frais_dossier: 25 }, { trip, settings, currencies });
  assert.equal(r2.totals.parPers, 570);
  assert.equal(r2.totals.arrondi, 15);
});

test('TVA sur marge : UE, hors UE, franchise', () => {
  const opt = { marge_mode: 'fixe_total', marge_valeur: 120, items: [{ type: 'autre', unite: 'forfait', prix_unitaire: 1000 }] };
  assert.equal(computeOption(opt, { trip, settings, currencies }).totals.tva, 20);
  assert.equal(computeOption(opt, { trip, settings, currencies, horsUE: true }).totals.tva, 0);
  assert.equal(computeOption(opt, { trip, settings: { ...settings, tva_regime: 'franchise' }, currencies }).totals.tva, 0);
});

test('prestations optionnelles, prix imposé et annulées', () => {
  const opt = {
    marge_mode: 'coef', marge_valeur: 10,
    items: [
      { type: 'vol', unite: 'forfait', prix_unitaire: 100, prix_vente_force: 150 },
      { type: 'activite', unite: 'personne', prix_unitaire: 50, optionnel: 1 },
      { type: 'transfert', unite: 'forfait', prix_unitaire: 999, statut: 'annule' },
    ],
  };
  const r = computeOption(opt, { trip, settings, currencies });
  assert.equal(r.totals.vente, 150);
  assert.equal(r.totals.optionnel, 110);
});

test('quantités par défaut', () => {
  assert.equal(defaultQty('personne', { nb_adultes: 2, nb_enfants: 1 }), 3);
  assert.equal(defaultQty('chambre_nuit', { nb_adultes: 3 }), 2);
  assert.equal(defaultQty('vehicule', { nb_adultes: 4 }), 1);
});

test('calculatrices', () => {
  assert.equal(sellFromCost(100, 'coef', 25), 125);
  assert.equal(sellFromCost(80, 'marque', 20), 100);
  assert.equal(vatOnMargin(120, 20), 20);
});

test('échéancier', () => {
  const s = paymentSchedule(1000, { acomptePct: 30, soldeJours: 30, depart: '2027-06-13', today: '2027-01-10' });
  assert.equal(s.length, 2);
  assert.equal(s[0].montant, 300);
  assert.equal(s[1].date_echeance, '2027-05-14');
  const late = paymentSchedule(1000, { acomptePct: 30, soldeJours: 30, depart: '2027-01-20', today: '2027-01-10' });
  assert.equal(late.length, 1);
});

test('durées de vol avec fuseaux horaires', () => {
  // Paris 13:30 → Bangkok 06:15 le lendemain (UTC+2 → UTC+7 en été)
  assert.equal(durationBetween('2027-07-12', '13:30', 'Europe/Paris', '2027-07-13', '06:15', 'Asia/Bangkok'), 705);
  // Paris → New York en hiver : 10:00 → 12:30 = 8 h 30
  assert.equal(itemDuration({ type: 'vol', date_debut: '2027-01-10', heure_debut: '10:00', tz_depart: 'Europe/Paris', date_fin: '2027-01-10', heure_fin: '12:30', tz_arrivee: 'America/New_York' }), 510);
  // changement d'heure : nuit du 28 mars 2027 en France
  const a = zonedToUtc('2027-03-28', '01:00', 'Europe/Paris');
  const b = zonedToUtc('2027-03-28', '04:00', 'Europe/Paris');
  assert.equal((b - a) / 60000, 120);
  assert.equal(fmtDuration(655), '10 h 55');
});

test('suggestions : oublis détectés', () => {
  const option = {
    items: [
      { type: 'vol', libelle: 'Aller', date_debut: '2027-06-13', heure_debut: '07:15', date_fin: '2027-06-13', heure_fin: '09:05', lieu_depart: 'CDG', lieu_arrivee: 'BCN', prix_unitaire: 100, tz_depart: 'Europe/Paris', tz_arrivee: 'Europe/Madrid' },
      { type: 'croisiere', libelle: 'Croisière', date_debut: '2027-06-13', date_fin: '2027-06-20', prix_unitaire: 1000 },
      { type: 'vol', libelle: 'Retour', date_debut: '2027-06-21', heure_debut: '13:40', date_fin: '2027-06-21', heure_fin: '15:35', lieu_depart: 'BCN', lieu_arrivee: 'CDG', prix_unitaire: 100 },
    ],
  };
  const s = runChecks({ trip, option, today: '2027-01-01' });
  const titles = s.map((x) => x.title).join(' | ');
  assert.match(titles, /Nuit du 20\/06 sans hébergement/);
  assert.match(titles, /Transfert à l'arrivée/);
  assert.match(titles, /Transfert vers l'aéroport/);
  assert.match(titles, /Arrivée le jour de l'embarquement/);
  assert.match(titles, /Assurance non proposée/);
  assert.equal(s[0].level, 'important');
  const nuit = s.find((x) => /sans hébergement/.test(x.title));
  assert.equal(nuit.add.date_debut, '2027-06-20');
});

test('suggestions : passeport et formalités hors UE', () => {
  const destination = { hors_ue: 1, formalites: 'Passeport + ESTA obligatoire' };
  const client = { id: 1, nom: 'Martin', passeport_expiration: '2027-09-01' };
  const option = { items: [{ type: 'hebergement', libelle: 'Hôtel', date_debut: '2027-06-13', date_fin: '2027-06-21', prix_unitaire: 1 }] };
  const s = runChecks({ trip, option, destination, client, today: '2027-01-01' });
  assert.ok(s.some((x) => /Passeport de Martin/.test(x.title) && x.level === 'important'));
  assert.ok(s.some((x) => /Formalités/.test(x.title)));
});

test('programme généré depuis les prestations', () => {
  const option = { items: [
    { type: 'vol', libelle: 'Aller', date_debut: '2027-06-13', lieu_depart: 'CDG', lieu_arrivee: 'BKK' },
    { type: 'hebergement', libelle: 'Hôtel Riva', date_debut: '2027-06-14', date_fin: '2027-06-16' },
  ] };
  const days = generateDays({ date_depart: '2027-06-13', date_retour: '2027-06-16' }, option);
  assert.equal(days.length, 4);
  assert.equal(days[0].titre, 'Paris ✈ Bangkok');
  assert.equal(days[1].lieu, 'Hôtel Riva');
});

test('tri chronologique des prestations sans heure', () => {
  const items = [
    { id: 1, type: 'hebergement', date_debut: '2027-06-13' },
    { id: 2, type: 'transfert', date_debut: '2027-06-13' },
    { id: 3, type: 'vol', date_debut: '2027-06-13', heure_debut: '07:15', date_fin: '2027-06-13', heure_fin: '09:05' },
    { id: 4, type: 'transfert', date_debut: '2027-06-21' },
    { id: 5, type: 'vol', date_debut: '2027-06-21', heure_debut: '13:40', date_fin: '2027-06-21', heure_fin: '15:35' },
    { id: 6, type: 'assurance' },
  ];
  assert.deepEqual(sortChrono(items).map((i) => i.id), [3, 2, 1, 4, 5, 6]);
});

test('lien de recherche hotels.com', () => {
  const u = new URL(hotelsComUrl({ destination: 'Barcelone', checkin: '2027-06-13', checkout: '2027-06-14', adults: 3, childAges: [6, 11], rooms: 2 }));
  assert.equal(u.hostname, 'fr.hotels.com');
  assert.equal(u.pathname, '/Hotel-Search');
  assert.equal(u.searchParams.get('destination'), 'Barcelone');
  assert.equal(u.searchParams.get('startDate'), '2027-06-13');
  assert.equal(u.searchParams.get('rooms'), '2');
  assert.equal(u.searchParams.get('adults'), '2,1');
  assert.equal(u.searchParams.get('children'), '1_6,2_11');
  assert.deepEqual(splitRooms(2, [], 5).adults, [1, 1]); // jamais plus de chambres que d'adultes
  assert.deepEqual(parseAges('4; 12', 3), [4, 12, 8]);
});

test('plages de nuits non couvertes', () => {
  const option = { items: [
    { type: 'hebergement', date_debut: '2027-06-14', date_fin: '2027-06-16' },
    { type: 'vol', date_debut: '2027-06-18', date_fin: '2027-06-19' },
  ] };
  const r = uncoveredRanges({ date_depart: '2027-06-13', date_retour: '2027-06-21' }, option);
  assert.deepEqual(r.map((x) => [x.from, x.to, x.nights.length]), [['2027-06-13', '2027-06-14', 1], ['2027-06-16', '2027-06-18', 2], ['2027-06-19', '2027-06-21', 2]]);
  assert.deepEqual(uncoveredRanges({ date_depart: '2027-06-13', date_retour: '2027-06-13' }, option), []);
});

import { defaultsBetween, returnOf, autoLabel, addMinutes } from '../assets/js/insert-defaults.js';

test('insertion entre deux éléments : transfert après un vol, avant un hôtel', () => {
  const vol = { type: 'vol', date_debut: '2027-06-13', heure_debut: '07:15', date_fin: '2027-06-13', heure_fin: '09:05', lieu_depart: 'CDG', lieu_arrivee: 'BCN' };
  const hotel = { type: 'hebergement', libelle: 'Hotel Casa Fuster 5* — 1 nuit', date_debut: '2027-06-13', date_fin: '2027-06-14' };
  const v = defaultsBetween(vol, hotel, 'transfert');
  assert.equal(v.date_debut, '2027-06-13');
  assert.equal(v.heure_debut, '09:35');
  assert.equal(v.lieu_depart, 'BCN');
  assert.equal(v.lieu_arrivee, 'Hotel Casa Fuster 5*');
  assert.match(v.libelle, /^Transfert Barcelone BCN → Hotel Casa Fuster/);
});

test('insertion : hôtel entre deux vols, vol après un hôtel, transfert avant un vol', () => {
  const aller = { type: 'vol', date_debut: '2027-06-13', date_fin: '2027-06-13', heure_fin: '09:05', lieu_arrivee: 'BKK' };
  const retour = { type: 'vol', date_debut: '2027-06-20', heure_debut: '13:40', lieu_depart: 'BKK', lieu_arrivee: 'CDG' };
  const h = defaultsBetween(aller, retour, 'hebergement');
  assert.deepEqual([h.date_debut, h.date_fin, h.libelle], ['2027-06-13', '2027-06-20', 'Hôtel — 7 nuits']);
  const hotel = { type: 'hebergement', libelle: 'Riva', date_debut: '2027-06-13', date_fin: '2027-06-20' };
  const t = defaultsBetween(hotel, retour, 'transfert');
  assert.deepEqual([t.date_debut, t.heure_debut, t.lieu_depart, t.lieu_arrivee], ['2027-06-20', '10:40', 'Riva', 'BKK']);
  const f = defaultsBetween(hotel, null, 'vol', { ville_depart: 'Paris' });
  assert.equal(f.date_debut, '2027-06-20');
  assert.equal(f.lieu_depart, undefined); // un nom d'hôtel n'est pas un aéroport
  const first = defaultsBetween(null, aller, 'vol', { date_depart: '2027-06-13', ville_depart: 'CDG' });
  assert.deepEqual([first.date_debut, first.lieu_depart, first.lieu_arrivee], ['2027-06-13', 'CDG', undefined]);
});

test('trajet retour et utilitaires', () => {
  const r = returnOf({ type: 'vol', lieu_depart: 'CDG', lieu_arrivee: 'BKK', compagnie: 'Thai Airways', date_debut: '2027-02-12' }, { date_retour: '2027-02-26' });
  assert.deepEqual([r.lieu_depart, r.lieu_arrivee, r.date_debut], ['BKK', 'CDG', '2027-02-26']);
  assert.equal(r.libelle, 'Vol Bangkok BKK → Paris CDG — Thai Airways');
  assert.equal(addMinutes('23:50', 30), '23:59');
  assert.equal(autoLabel({ type: 'croisiere', navire: 'MSC Europa', date_debut: '2027-06-14', date_fin: '2027-06-21' }), 'Croisière MSC Europa — 7 nuits');
});

test('insertion : pas de trajet « A → A », libellés par défaut', () => {
  const vol = { type: 'vol', date_debut: '2027-06-13', heure_fin: '09:05', lieu_arrivee: 'BCN' };
  const tr = { type: 'transfert', date_debut: '2027-06-13', lieu_depart: 'BCN', lieu_arrivee: 'Hôtel' };
  const v = defaultsBetween(vol, tr, 'transfert');
  assert.equal(v.lieu_depart, 'BCN');
  assert.equal(v.lieu_arrivee, undefined);
  assert.equal(defaultsBetween(tr, null, 'activite').libelle, 'Excursion');
});
