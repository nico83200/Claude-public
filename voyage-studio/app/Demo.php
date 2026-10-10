<?php
declare(strict_types=1);

/** Données de démonstration (facultatives, proposées à l'installation). */
final class Demo
{
    public static function seed(Repository $r, Database $db): void
    {
        $db->transaction(function () use ($r) {
            $y = (int)date('Y') + (date('n') > 6 ? 1 : 0);

            $c1 = $r->create('clients', ['civilite' => 'Mme', 'nom' => 'Martin', 'prenom' => 'Claire', 'email' => 'claire.martin@example.com',
                'telephone' => '06 12 34 56 78', 'passeport_expiration' => "$y-09-15", 'nationalite' => 'Française',
                'preferences' => 'Aime les croisières et les hôtels de charme. Budget ~4 000 € pour 2.', 'source' => 'Bouche-à-oreille']);
            $c2 = $r->create('clients', ['civilite' => 'M.', 'nom' => 'Martin', 'prenom' => 'Paul', 'passeport_expiration' => ($y + 6) . '-01-10']);
            $r->create('clients', ['civilite' => 'M.', 'nom' => 'Durand', 'prenom' => 'Hugo', 'email' => 'hugo.durand@example.com',
                'preferences' => 'Voyages aventure, trek, photo.']);

            $sAf = $r->create('suppliers', ['nom' => 'Air France', 'type' => 'aerien', 'commission_pct' => 0]);
            $sMsc = $r->create('suppliers', ['nom' => 'MSC Croisières', 'type' => 'croisiere', 'commission_pct' => 12,
                'delai_paiement' => '25 % à la réservation, solde J-45', 'conditions_annulation' => "J-90 : 10 %\nJ-60 : 30 %\nJ-30 : 60 %\nJ-15 : 100 %"]);
            $sRec = $r->create('suppliers', ['nom' => 'Sun Transfers Barcelone', 'type' => 'transfert', 'devise' => 'EUR']);
            $sHot = $r->create('suppliers', ['nom' => 'Hotel Casa Fuster', 'type' => 'hotel']);
            $r->create('suppliers', ['nom' => 'Chapka Assurances', 'type' => 'assurance', 'commission_pct' => 20]);

            $dMed = $r->create('destinations', ['nom' => 'Méditerranée occidentale', 'pays' => 'Espagne / France / Italie', 'region' => 'Europe',
                'aeroport' => 'BCN', 'fuseau' => 'Europe/Madrid', 'devise' => 'EUR', 'langue' => 'Espagnol, catalan, italien',
                'vol_duree' => '1 h 45', 'budget_jour' => 60, 'hors_ue' => 0, 'mois_ideaux' => '001122221100',
                'formalites' => 'Carte d\'identité ou passeport en cours de validité.']);
            $r->create('destinations', ['nom' => 'Thaïlande', 'pays' => 'Thaïlande', 'region' => 'Asie du Sud-Est', 'aeroport' => 'BKK',
                'fuseau' => 'Asia/Bangkok', 'devise' => 'THB', 'langue' => 'Thaï', 'vol_duree' => '11 h 30 (direct)', 'budget_jour' => 45,
                'hors_ue' => 1, 'mois_ideaux' => '222110000122',
                'formalites' => 'Passeport valide 6 mois après le retour. Exemption de visa pour séjour touristique (vérifier la durée en vigueur).',
                'sante' => 'Aucun vaccin obligatoire. Hépatite A/B, typhoïde recommandés. Protection anti-moustiques (dengue).']);
            $r->create('destinations', ['nom' => 'New York', 'pays' => 'États-Unis', 'region' => 'Amérique du Nord', 'aeroport' => 'JFK',
                'fuseau' => 'America/New_York', 'devise' => 'USD', 'vol_duree' => '8 h 30', 'budget_jour' => 120, 'hors_ue' => 1,
                'mois_ideaux' => '001122112210', 'formalites' => 'Passeport biométrique + ESTA obligatoire (à demander au moins 72 h avant).']);

            $trip = $r->create('trips', ['reference' => 'VS' . substr((string)$y, 2) . '-0001', 'titre' => 'Croisière Méditerranée — Famille Martin',
                'client_id' => $c1['id'], 'destination_id' => $dMed['id'], 'statut' => 'devis', 'date_depart' => "$y-06-13", 'date_retour' => "$y-06-21",
                'ville_depart' => 'Paris', 'nb_adultes' => 2, 'nb_enfants' => 0, 'budget' => 4000, 'date_option_limite' => date('Y-m-d', strtotime('+5 days')),
                'demande' => 'Croisière d\'une semaine en juin, cabine balcon, départ de Paris. Souhaitent 1 nuit à Barcelone avant l\'embarquement.']);
            $r->create('travelers', ['trip_id' => $trip['id'], 'client_id' => $c1['id']]);
            $r->create('travelers', ['trip_id' => $trip['id'], 'client_id' => $c2['id']]);

            $optA = $r->create('options', ['trip_id' => $trip['id'], 'nom' => 'Option A — Croisière balcon + nuit à Barcelone', 'marge_mode' => 'coef',
                'marge_valeur' => 12, 'frais_dossier' => 30, 'arrondi' => 10, 'ordre' => 0,
                'resume' => '7 nuits à bord du MSC World Europa en cabine balcon, pension complète, précédées d\'une nuit dans un hôtel de charme à Barcelone.',
                'inclus' => "Vols Paris – Barcelone A/R\nTransferts privés\n1 nuit à l'hôtel Casa Fuster 5* avec petit-déjeuner\nCroisière 7 nuits en pension complète\nTaxes portuaires",
                'non_inclus' => "Forfait boissons\nExcursions\nForfait de séjour à bord (pourboires)\nAssurances"]);
            $items = [
                ['type' => 'vol', 'libelle' => 'Vol Paris CDG → Barcelone BCN', 'supplier_id' => $sAf['id'], 'compagnie' => 'Air France', 'numero' => 'AF1348',
                    'date_debut' => "$y-06-13", 'heure_debut' => '07:15', 'date_fin' => "$y-06-13", 'heure_fin' => '09:05', 'lieu_depart' => 'CDG', 'lieu_arrivee' => 'BCN',
                    'tz_depart' => 'Europe/Paris', 'tz_arrivee' => 'Europe/Madrid', 'bagages' => '1 × 23 kg', 'unite' => 'personne', 'prix_unitaire' => 95, 'taxes' => 96, 'statut' => 'option'],
                ['type' => 'transfert', 'libelle' => 'Transfert aéroport → hôtel', 'supplier_id' => $sRec['id'], 'mode_transfert' => 'prive', 'date_debut' => "$y-06-13",
                    'duree_min' => 30, 'distance_km' => 15, 'unite' => 'vehicule', 'prix_unitaire' => 55, 'lieu_depart' => 'BCN', 'lieu_arrivee' => 'Hôtel'],
                ['type' => 'hebergement', 'libelle' => 'Hotel Casa Fuster 5*', 'supplier_id' => $sHot['id'], 'date_debut' => "$y-06-13", 'date_fin' => "$y-06-14",
                    'categorie' => '5*', 'chambre' => 'Double Deluxe', 'pension' => 'BB', 'unite' => 'chambre_nuit', 'prix_unitaire' => 290, 'taxes' => 13.2],
                ['type' => 'transfert', 'libelle' => 'Transfert hôtel → port', 'supplier_id' => $sRec['id'], 'mode_transfert' => 'prive', 'date_debut' => "$y-06-14",
                    'duree_min' => 20, 'unite' => 'vehicule', 'prix_unitaire' => 45],
                ['type' => 'croisiere', 'libelle' => 'Croisière MSC World Europa — 7 nuits', 'supplier_id' => $sMsc['id'], 'compagnie' => 'MSC Croisières',
                    'navire' => 'MSC World Europa', 'date_debut' => "$y-06-14", 'date_fin' => "$y-06-21", 'lieu_depart' => 'Barcelone', 'lieu_arrivee' => 'Barcelone',
                    'categorie' => 'Balcon Fantastica', 'chambre' => 'Cabine balcon', 'pension' => 'FB', 'mode_tarif' => 'commission', 'commission_pct' => 12,
                    'unite' => 'personne', 'prix_unitaire' => 1149, 'taxes' => 300, 'statut' => 'option', 'date_limite_option' => date('Y-m-d', strtotime('+3 days'))],
                ['type' => 'transfert', 'libelle' => 'Transfert port → aéroport', 'supplier_id' => $sRec['id'], 'mode_transfert' => 'prive', 'date_debut' => "$y-06-21",
                    'duree_min' => 25, 'unite' => 'vehicule', 'prix_unitaire' => 50],
                ['type' => 'vol', 'libelle' => 'Vol Barcelone BCN → Paris CDG', 'supplier_id' => $sAf['id'], 'compagnie' => 'Air France', 'numero' => 'AF1849',
                    'date_debut' => "$y-06-21", 'heure_debut' => '13:40', 'date_fin' => "$y-06-21", 'heure_fin' => '15:35', 'lieu_depart' => 'BCN', 'lieu_arrivee' => 'CDG',
                    'tz_depart' => 'Europe/Madrid', 'tz_arrivee' => 'Europe/Paris', 'bagages' => '1 × 23 kg', 'unite' => 'personne', 'prix_unitaire' => 88, 'taxes' => 60],
                ['type' => 'activite', 'libelle' => 'Excursion Florence & Pise (escale La Spezia)', 'optionnel' => 1, 'date_debut' => "$y-06-17",
                    'unite' => 'personne', 'prix_unitaire' => 119, 'mode_tarif' => 'commission', 'commission_pct' => 10],
            ];
            foreach ($items as $i => $it) {
                $r->create('items', $it + ['option_id' => $optA['id'], 'ordre' => $i]);
            }
            $days = [
                [1, 'Paris ✈ Barcelone', 'Barcelone', 'Vol matinal, transfert privé et après-midi libre dans le quartier de Gràcia.'],
                [2, 'Embarquement', 'À bord', 'Matinée libre puis transfert au port et embarquement à bord du MSC World Europa.'],
                [3, 'Marseille', 'À bord', 'Escale à Marseille : Vieux-Port, Notre-Dame-de-la-Garde.'],
                [4, 'Gênes', 'À bord', ''], [5, 'La Spezia', 'À bord', 'Possibilité d\'excursion à Florence et Pise.'],
                [6, 'Civitavecchia (Rome)', 'À bord', ''], [7, 'Messine', 'À bord', ''], [8, 'Journée en mer', 'À bord', ''],
                [9, 'Barcelone ✈ Paris', '', 'Débarquement, transfert à l\'aéroport et vol retour.'],
            ];
            foreach ($days as [$j, $t, $l, $d]) {
                $r->create('days', ['option_id' => $optA['id'], 'jour' => $j, 'titre' => $t, 'lieu' => $l, 'description' => $d]);
            }

            $optB = $r->duplicate('options', $optA['id'], ['nom' => 'Option B — Croisière cabine intérieure, sans nuit à Barcelone', 'ordre' => 1]);
            foreach ($r->list('items', ['option_id' => $optB['id']]) as $it) {
                if ($it['type'] === 'hebergement' || ($it['type'] === 'transfert' && $it['libelle'] === 'Transfert hôtel → port')) {
                    $r->delete('items', $it['id']);
                } elseif ($it['type'] === 'croisiere') {
                    $r->update('items', $it['id'], ['categorie' => 'Intérieure Bella', 'chambre' => 'Cabine intérieure', 'prix_unitaire' => 799]);
                } elseif ($it['type'] === 'vol' && $it['numero'] === 'AF1348') {
                    $r->update('items', $it['id'], ['date_debut' => "$y-06-14", 'date_fin' => "$y-06-14"]);
                } elseif ($it['type'] === 'transfert') {
                    $r->update('items', $it['id'], ['libelle' => str_replace('hôtel', 'port', $it['libelle'])]);
                }
            }
            $r->update('trips', $trip['id'], ['selected_option_id' => $optA['id']]);
        });
    }
}
