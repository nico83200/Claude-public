<?php
declare(strict_types=1);

/**
 * Catalogue de départ : destinations prisées au départ de la France et fournisseurs courants.
 *
 * Saisonnalité (mois_ideaux) : 12 caractères de janvier à décembre — 2 = idéal, 1 = correct, 0 = déconseillé.
 * Formalités : indications générales pour un ressortissant français, à vérifier sur diplomatie.gouv.fr
 * (elles évoluent régulièrement). Budget sur place : ordre de grandeur en €/pers./jour hors hébergement.
 * Aucun tarif ni taux de commission n'est fourni : ils dépendent de vos contrats.
 */
final class Catalog
{
    private const VERIF = ' À vérifier sur diplomatie.gouv.fr avant chaque vente.';

    /** [nom, pays, région, aéroport, fuseau, hors UE, devise, langue, vol depuis Paris, budget/jour, saisons, climat, formalités, santé, description] */
    private const DESTINATIONS = [
        // Europe
        ['Barcelone', 'Espagne', 'Europe du Sud', 'BCN', 'Europe/Madrid', 0, 'EUR', 'Espagnol, catalan', '1 h 45', 70, '011222222110', 'Méditerranéen : étés chauds, hivers doux.', 'Carte d\'identité ou passeport en cours de validité.', 'Carte européenne d\'assurance maladie (CEAM) conseillée.', 'Gaudí, quartier gothique, plages urbaines et gastronomie catalane.'],
        ['Andalousie (Séville, Grenade, Malaga)', 'Espagne', 'Europe du Sud', 'SVQ', 'Europe/Madrid', 0, 'EUR', 'Espagnol', '2 h 15', 60, '112221012211', 'Très chaud en été (plus de 40 °C à Séville), doux en hiver.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Alhambra, Mezquita de Cordoue, villages blancs, Costa del Sol.'],
        ['Îles Canaries', 'Espagne', 'Europe du Sud', 'TFS', 'Atlantic/Canary', 0, 'EUR', 'Espagnol', '4 h 15', 55, '222222222222', 'Printemps éternel : 20 à 28 °C toute l\'année.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Soleil d\'hiver à 4 h de Paris : volcans, randonnées, plages.'],
        ['Baléares (Majorque, Minorque, Ibiza)', 'Espagne', 'Europe du Sud', 'PMI', 'Europe/Madrid', 0, 'EUR', 'Espagnol, catalan', '1 h 50', 70, '000122222100', 'Méditerranéen ; beaucoup de structures fermées en hiver.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Criques turquoise, villages de pierre, vie nocturne à Ibiza.'],
        ['Lisbonne', 'Portugal', 'Europe du Sud', 'LIS', 'Europe/Lisbon', 0, 'EUR', 'Portugais', '2 h 30', 60, '112222222211', 'Doux et ensoleillé, hivers pluvieux mais tempérés.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Collines, tramways, Belém, Sintra à proximité.'],
        ['Madère', 'Portugal', 'Europe du Sud', 'FNC', 'Atlantic/Madeira', 0, 'EUR', 'Portugais', '3 h 45', 60, '222222222222', 'Doux toute l\'année (18 à 25 °C).', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Île-jardin : levadas, falaises, Funchal.'],
        ['Açores', 'Portugal', 'Europe du Sud', 'PDL', 'Atlantic/Azores', 0, 'EUR', 'Portugais', '4 h (escale fréquente)', 60, '001122221000', 'Océanique, changeant ; meilleure période de mai à septembre.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Volcans, lacs de cratère, observation des baleines.'],
        ['Rome', 'Italie', 'Europe du Sud', 'FCO', 'Europe/Rome', 0, 'EUR', 'Italien', '2 h', 80, '112221012211', 'Chaud en été, doux au printemps et à l\'automne.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Colisée, Vatican, fontaines et trattorias. Taxe de séjour à régler sur place.'],
        ['Venise & Dolomites', 'Italie', 'Europe du Sud', 'VCE', 'Europe/Rome', 0, 'EUR', 'Italien', '1 h 40', 90, '011222122100', 'Hivers humides, étés chauds ; haute montagne l\'été.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Canaux et lagune ; taxe d\'accès à Venise certains jours.'],
        ['Sicile', 'Italie', 'Europe du Sud', 'CTA', 'Europe/Rome', 0, 'EUR', 'Italien', '2 h 30', 65, '011222222110', 'Méditerranéen chaud ; très chaud en juillet-août.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Etna, Taormine, temples grecs, Palerme.'],
        ['Côte amalfitaine & Naples', 'Italie', 'Europe du Sud', 'NAP', 'Europe/Rome', 0, 'EUR', 'Italien', '2 h 15', 90, '001222221100', 'Méditerranéen ; saison de mai à octobre.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Positano, Amalfi, Capri, Pompéi.'],
        ['Sardaigne', 'Italie', 'Europe du Sud', 'OLB', 'Europe/Rome', 0, 'EUR', 'Italien', '1 h 45', 75, '000122222100', 'Méditerranéen ; saison balnéaire de mai à octobre.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Plages caribéennes, Costa Smeralda, nuraghes.'],
        ['Grèce — Cyclades', 'Grèce', 'Europe du Sud', 'JTR', 'Europe/Athens', 0, 'EUR', 'Grec', '3 h 30', 80, '000122222100', 'Sec et ensoleillé, vent du meltem en été.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Santorin, Mykonos, Naxos, Paros : villages blancs et ferries.'],
        ['Crète', 'Grèce', 'Europe du Sud', 'HER', 'Europe/Athens', 0, 'EUR', 'Grec', '3 h 30', 60, '001222222100', 'Le plus chaud et le plus long été grec.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Gorges de Samaria, Knossos, plages d\'Elafonissi.'],
        ['Croatie (Dalmatie)', 'Croatie', 'Europe du Sud', 'SPU', 'Europe/Zagreb', 0, 'EUR', 'Croate', '2 h 10', 70, '000122222100', 'Méditerranéen ; saison de mai à septembre.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Split, Dubrovnik, îles et parc de Plitvice.'],
        ['Malte', 'Malte', 'Europe du Sud', 'MLA', 'Europe/Malta', 0, 'EUR', 'Maltais, anglais', '2 h 40', 60, '112222222211', 'Ensoleillé, hivers très doux.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'La Valette, Gozo, plongée, séjours linguistiques.'],
        ['Chypre', 'Chypre', 'Europe du Sud', 'LCA', 'Asia/Nicosia', 0, 'EUR', 'Grec, turc, anglais', '4 h 15', 60, '112222122211', 'Très ensoleillé ; très chaud en juillet-août.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Plages, villages du Troodos, sites antiques.'],
        ['Londres', 'Royaume-Uni', 'Europe du Nord', 'LHR', 'Europe/London', 1, 'GBP', 'Anglais', '1 h 15 (2 h 20 en Eurostar)', 110, '112222222112', 'Océanique, frais et changeant.', 'Passeport obligatoire + autorisation de voyage électronique (ETA) à demander en ligne avant le départ.', 'Couverture santé : CEAM acceptée au Royaume-Uni pour les soins nécessaires ; assurance conseillée.', 'Musées gratuits, comédies musicales, shopping.'],
        ['Écosse', 'Royaume-Uni', 'Europe du Nord', 'EDI', 'Europe/London', 1, 'GBP', 'Anglais', '1 h 50', 90, '000112221000', 'Frais et humide ; jours très longs en été.', 'Passeport obligatoire + ETA à demander en ligne avant le départ.', 'Anti-moustiques (midges) dans les Highlands en été.', 'Édimbourg, Highlands, île de Skye, whisky.'],
        ['Irlande', 'Irlande', 'Europe du Nord', 'DUB', 'Europe/Dublin', 0, 'EUR', 'Anglais, irlandais', '1 h 30', 90, '000112221000', 'Océanique doux et pluvieux.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Dublin, falaises de Moher, Connemara, road-trips.'],
        ['Islande', 'Islande', 'Europe du Nord', 'KEF', 'Atlantic/Reykjavik', 1, 'ISK', 'Islandais', '3 h 30', 130, '111112221111', 'Frais et venteux ; soleil de minuit en été, aurores en hiver.', 'Carte d\'identité ou passeport en cours de validité (espace Schengen).', 'CEAM acceptée (EEE) ; assurance conseillée.', 'Cascades, glaciers, geysers, aurores boréales de septembre à mars.'],
        ['Laponie finlandaise', 'Finlande', 'Europe du Nord', 'RVN', 'Europe/Helsinki', 0, 'EUR', 'Finnois, same', '3 h 30 (vols directs en hiver)', 120, '222100001122', 'Grand froid en hiver (-15 à -30 °C) ; nuit polaire en décembre.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée ; équipement grand froid indispensable.', 'Chiens de traîneau, motoneige, aurores boréales, village du Père Noël.'],
        ['Norvège — Fjords & Lofoten', 'Norvège', 'Europe du Nord', 'OSL', 'Europe/Oslo', 1, 'NOK', 'Norvégien', '2 h', 130, '111112221011', 'Frais ; soleil de minuit en juin, aurores en hiver dans le nord.', 'Carte d\'identité ou passeport en cours de validité (espace Schengen).', 'CEAM acceptée (EEE).', 'Fjords, croisières côtières, Lofoten, Tromsø en hiver.'],
        ['Prague & Vienne', 'Tchéquie / Autriche', 'Europe centrale', 'PRG', 'Europe/Prague', 0, 'CZK', 'Tchèque, allemand', '1 h 40', 70, '112222222112', 'Continental : étés chauds, hivers froids ; marchés de Noël.', 'Carte d\'identité ou passeport en cours de validité.', 'CEAM conseillée.', 'Villes impériales, musique classique, marchés de Noël en décembre.'],
        ['Suisse', 'Suisse', 'Europe centrale', 'GVA', 'Europe/Zurich', 1, 'CHF', 'Allemand, français, italien', '1 h 05 (ou TGV Lyria)', 150, '221222222112', 'Montagnard : ski en hiver, randonnée en été.', 'Carte d\'identité ou passeport en cours de validité (espace Schengen).', 'CEAM acceptée.', 'Trains panoramiques, lacs, Alpes ; pays cher.'],
        ['Corse', 'France', 'France', 'AJA', 'Europe/Paris', 0, 'EUR', 'Français, corse', '1 h 35', 80, '000122222100', 'Méditerranéen ; montagne fraîche, littoral chaud.', 'Carte d\'identité.', '—', 'GR20, calanques de Piana, Bonifacio, plages.'],
        // Outre-mer
        ['Guadeloupe', 'France (outre-mer)', 'Caraïbes', 'PTP', 'America/Guadeloupe', 0, 'EUR', 'Français, créole', '8 h 30', 60, '222221111122', 'Tropical : saison sèche de décembre à mai, cyclones possibles d\'août à octobre.', 'Carte d\'identité.', 'Dengue et chikungunya : protection anti-moustiques.', 'Basse-Terre volcanique, Grande-Terre balnéaire, Marie-Galante, les Saintes.'],
        ['Martinique', 'France (outre-mer)', 'Caraïbes', 'FDF', 'America/Martinique', 0, 'EUR', 'Français, créole', '8 h 30', 60, '222221111122', 'Tropical : saison sèche de décembre à mai, cyclones possibles d\'août à octobre.', 'Carte d\'identité.', 'Protection anti-moustiques (dengue).', 'Montagne Pelée, Anses-d\'Arlet, distilleries de rhum.'],
        ['La Réunion', 'France (outre-mer)', 'Océan Indien', 'RUN', 'Indian/Reunion', 0, 'EUR', 'Français, créole', '11 h', 60, '111122222221', 'Tropical ; hiver austral sec (mai-novembre) idéal pour la randonnée, cyclones possibles en janvier-mars.', 'Carte d\'identité.', 'Protection anti-moustiques (dengue).', 'Piton de la Fournaise, cirques de Mafate, Cilaos et Salazie.'],
        ['Polynésie française (Tahiti, Bora-Bora)', 'France (outre-mer)', 'Pacifique', 'PPT', 'Pacific/Tahiti', 1, 'XPF', 'Français, tahitien', '22 h (escale à Los Angeles ou San Francisco)', 120, '111122222211', 'Tropical ; saison sèche de mai à octobre.', 'Carte d\'identité suffisante en vol direct via la France ; passeport + ESTA si escale aux États-Unis.', 'Protection anti-moustiques (dengue).', 'Lagons, bungalows sur pilotis, croisières entre les îles. Territoire hors UE.'],
        // Méditerranée et Afrique du Nord
        ['Marrakech & Sud marocain', 'Maroc', 'Afrique du Nord', 'RAK', 'Africa/Casablanca', 1, 'MAD', 'Arabe, berbère, français', '3 h 30', 50, '222221012222', 'Sec ; très chaud en été, nuits fraîches en hiver et dans le désert.', 'Passeport valide pendant toute la durée du séjour ; pas de visa pour un séjour touristique de moins de 3 mois.', 'Pas de vaccin obligatoire.', 'Médina, riads, Atlas, désert d\'Agafay ou de Merzouga.'],
        ['Agadir & Essaouira', 'Maroc', 'Afrique du Nord', 'AGA', 'Africa/Casablanca', 1, 'MAD', 'Arabe, berbère, français', '3 h 30', 45, '222222222222', 'Ensoleillé toute l\'année, tempéré par l\'Atlantique.', 'Passeport valide pendant toute la durée du séjour ; pas de visa pour un séjour touristique de moins de 3 mois.', 'Pas de vaccin obligatoire.', 'Balnéaire d\'hiver, surf, golf.'],
        ['Djerba & Tunisie', 'Tunisie', 'Afrique du Nord', 'DJE', 'Africa/Tunis', 1, 'TND', 'Arabe, français', '2 h 45', 40, '112222122211', 'Méditerranéen chaud ; très chaud en juillet-août.', 'Passeport valide (carte d\'identité acceptée sous conditions pour certains voyages organisés) ; pas de visa pour un séjour de moins de 3 mois.', 'Pas de vaccin obligatoire.', 'Clubs balnéaires, thalasso, désert du Sud tunisien.'],
        ['Égypte — Croisière sur le Nil', 'Égypte', 'Afrique du Nord', 'LXR', 'Africa/Cairo', 1, 'EGP', 'Arabe', '4 h 30 à 5 h', 50, '222110001222', 'Désertique ; très chaud de mai à septembre.', 'Passeport valide 6 mois + visa (en ligne ou à l\'arrivée).', 'Eau en bouteille ; hépatite A conseillée.', 'Louxor, Karnak, vallée des Rois, Assouan, Abou Simbel.'],
        ['Égypte — Mer Rouge (Hurghada, Marsa Alam)', 'Égypte', 'Afrique du Nord', 'HRG', 'Africa/Cairo', 1, 'EGP', 'Arabe', '4 h 30 à 5 h 30', 45, '122222112222', 'Soleil toute l\'année ; vent fréquent en hiver.', 'Passeport valide 6 mois + visa (en ligne ou à l\'arrivée ; régime particulier possible pour le Sinaï).', 'Eau en bouteille.', 'Plongée sur les récifs, clubs tout inclus.'],
        ['Turquie — Istanbul & Cappadoce', 'Turquie', 'Europe / Asie', 'IST', 'Europe/Istanbul', 1, 'TRY', 'Turc', '3 h 30', 55, '112221122211', 'Continental en Cappadoce (hivers froids), doux à Istanbul.', 'Carte d\'identité ou passeport ; pas de visa pour un séjour touristique de moins de 90 jours.', 'Pas de vaccin obligatoire.', 'Sainte-Sophie, Bosphore, montgolfières en Cappadoce.'],
        ['Turquie — Côte égéenne & Antalya', 'Turquie', 'Europe / Asie', 'AYT', 'Europe/Istanbul', 1, 'TRY', 'Turc', '3 h 45', 45, '001122222210', 'Méditerranéen chaud ; saison d\'avril à octobre.', 'Carte d\'identité ou passeport ; pas de visa pour un séjour touristique de moins de 90 jours.', 'Pas de vaccin obligatoire.', 'Clubs tout inclus, sites antiques, plages.'],
        ['Jordanie', 'Jordanie', 'Moyen-Orient', 'AMM', 'Asia/Amman', 1, 'JOD', 'Arabe', '4 h 45', 70, '112221012211', 'Désertique ; chaud en été, froid la nuit en hiver.', 'Passeport valide 6 mois + visa (le Jordan Pass inclut le visa et l\'entrée à Petra).', 'Pas de vaccin obligatoire.', 'Petra, Wadi Rum, mer Morte.'],
        ['Dubaï & Abu Dhabi', 'Émirats arabes unis', 'Moyen-Orient', 'DXB', 'Asia/Dubai', 1, 'AED', 'Arabe, anglais', '6 h 45', 110, '222210000122', 'Désertique ; étés extrêmement chauds (plus de 40 °C).', 'Passeport valide 6 mois ; visa gratuit délivré à l\'arrivée pour un court séjour.', 'Pas de vaccin obligatoire.', 'Gratte-ciel, désert, plages, Louvre Abu Dhabi ; escale idéale vers l\'Asie.'],
        ['Oman', 'Oman', 'Moyen-Orient', 'MCT', 'Asia/Muscat', 1, 'OMR', 'Arabe, anglais', '7 h (escale fréquente)', 90, '222100000122', 'Désertique ; très chaud de mai à septembre.', 'Passeport valide 6 mois ; visa ou exemption de courte durée selon la réglementation en vigueur.', 'Pas de vaccin obligatoire.', 'Fjords du Musandam, désert des Wahiba, forts et wadis.'],
        // Océan Indien et Afrique
        ['Île Maurice', 'Maurice', 'Océan Indien', 'MRU', 'Indian/Mauritius', 1, 'MUR', 'Anglais, français, créole', '11 h 30', 60, '111222222222', 'Tropical ; été austral chaud et humide (cyclones possibles de janvier à mars).', 'Passeport valide pendant le séjour + billet retour ; pas de visa pour un séjour touristique.', 'Protection anti-moustiques.', 'Lagons, hôtels de luxe, voyages de noces.'],
        ['Seychelles', 'Seychelles', 'Océan Indien', 'SEZ', 'Indian/Mahe', 1, 'SCR', 'Créole, anglais, français', '10 h (escale fréquente)', 120, '122222122222', 'Tropical chaud toute l\'année ; alizés de mai à septembre.', 'Passeport valide + autorisation de voyage électronique payante à demander avant le départ.', 'Protection anti-moustiques.', 'Plages de granit, Praslin, La Digue.'],
        ['Maldives', 'Maldives', 'Océan Indien', 'MLE', 'Indian/Maldives', 1, 'USD', 'Divehi, anglais', '11 h (escale)', 150, '222221111122', 'Tropical ; saison sèche de décembre à avril, mousson de mai à novembre.', 'Passeport valide 6 mois ; visa touristique gratuit délivré à l\'arrivée ; formulaire de santé et de douane en ligne.', 'Protection anti-moustiques.', 'Îles-hôtels, villas sur pilotis, plongée avec les raies manta.'],
        ['Zanzibar & Tanzanie (safari)', 'Tanzanie', 'Afrique de l\'Est', 'ZNZ', 'Africa/Dar_es_Salaam', 1, 'TZS', 'Swahili, anglais', '10 h (escale)', 90, '221002222222', 'Grandes pluies d\'avril à mai.', 'Passeport valide 6 mois + visa (en ligne) ; assurance de voyage obligatoire pour Zanzibar.', 'Fièvre jaune : certificat exigé si vous venez d\'un pays à risque ; traitement antipaludique recommandé.', 'Serengeti, Ngorongoro, plages de Zanzibar.'],
        ['Kenya (safari)', 'Kenya', 'Afrique de l\'Est', 'NBO', 'Africa/Nairobi', 1, 'KES', 'Swahili, anglais', '8 h 30', 100, '221002222222', 'Saisons sèches de janvier à mars et de juillet à octobre (grande migration).', 'Passeport valide 6 mois + autorisation de voyage électronique (eTA) avant le départ.', 'Fièvre jaune recommandée ; traitement antipaludique.', 'Masai Mara, Amboseli, plages de Diani.'],
        ['Afrique du Sud', 'Afrique du Sud', 'Afrique australe', 'CPT', 'Africa/Johannesburg', 1, 'ZAR', 'Anglais, afrikaans, zoulou…', '11 h 30', 60, '222111122222', 'Hémisphère sud : été de novembre à mars ; safaris idéaux en saison sèche (mai à octobre).', 'Passeport valide 30 jours après le départ, avec au moins 2 pages vierges ; pas de visa pour un court séjour.', 'Traitement antipaludique pour le parc Kruger selon la saison.', 'Le Cap, route des Jardins, parc Kruger, vignobles.'],
        ['Namibie', 'Namibie', 'Afrique australe', 'WDH', 'Africa/Windhoek', 1, 'NAD', 'Anglais, afrikaans', '12 h (escale)', 80, '111122222221', 'Désertique ; saison sèche de mai à octobre idéale pour observer la faune.', 'Passeport valide 6 mois avec pages vierges + visa (régime modifié récemment).', 'Traitement antipaludique au nord selon la saison.', 'Dunes de Sossusvlei, Etosha, Skeleton Coast ; autotours en 4x4.'],
        ['Sénégal', 'Sénégal', 'Afrique de l\'Ouest', 'DSS', 'Africa/Dakar', 1, 'XOF', 'Français, wolof', '5 h 30', 50, '222221000122', 'Saison sèche de novembre à juin ; saison des pluies de juillet à octobre.', 'Passeport valide 6 mois ; pas de visa pour un court séjour.', 'Fièvre jaune recommandée ; traitement antipaludique.', 'Dakar, île de Gorée, Saloum, Casamance.'],
        ['Cap-Vert', 'Cap-Vert', 'Afrique de l\'Ouest', 'SID', 'Atlantic/Cape_Verde', 1, 'CVE', 'Portugais, créole', '6 h', 50, '222222111222', 'Sec et ensoleillé, alizés ; quelques pluies d\'août à octobre.', 'Passeport valide 6 mois ; enregistrement préalable en ligne (EASE) et taxe aéroportuaire.', 'Pas de vaccin obligatoire.', 'Sal et Boa Vista pour la plage, Santo Antão pour la randonnée.'],
        // Asie
        ['Thaïlande', 'Thaïlande', 'Asie du Sud-Est', 'BKK', 'Asia/Bangkok', 1, 'THB', 'Thaï', '11 h 30 (direct)', 45, '222110000122', 'Tropical : saison sèche de novembre à mars ; mousson ensuite (le golfe, avec Koh Samui, a un calendrier différent).', 'Passeport valide 6 mois ; exemption de visa pour un séjour touristique (durée à vérifier) ; carte d\'arrivée numérique à remplir avant le vol.', 'Hépatite A/B et typhoïde conseillées ; protection contre la dengue.', 'Bangkok, Chiang Mai, îles d\'Andaman, temples et cuisine de rue.'],
        ['Vietnam', 'Vietnam', 'Asie du Sud-Est', 'HAN', 'Asia/Ho_Chi_Minh', 1, 'VND', 'Vietnamien', '12 h (direct)', 40, '222211111122', 'Climat différent du nord au sud ; le centre est pluvieux d\'octobre à décembre.', 'Passeport valide 6 mois ; exemption de visa pour un court séjour ou e-visa au-delà (durées à vérifier).', 'Hépatite A/B, typhoïde ; protection contre la dengue.', 'Baie d\'Halong, Hoi An, delta du Mékong.'],
        ['Cambodge (Angkor)', 'Cambodge', 'Asie du Sud-Est', 'REP', 'Asia/Phnom_Penh', 1, 'KHR', 'Khmer', '14 h (escale)', 40, '222100000122', 'Saison sèche de novembre à mars.', 'Passeport valide 6 mois + visa (e-visa ou à l\'arrivée).', 'Hépatite A/B ; traitement antipaludique hors des villes.', 'Temples d\'Angkor, Phnom Penh, côte de Kampot.'],
        ['Bali & Indonésie', 'Indonésie', 'Asie du Sud-Est', 'DPS', 'Asia/Makassar', 1, 'IDR', 'Indonésien, balinais', '16 h (escale)', 45, '111122222211', 'Saison sèche d\'avril à octobre.', 'Passeport valide 6 mois + visa à l\'arrivée ou en ligne (e-VOA) ; taxe touristique de Bali.', 'Hépatite A/B, typhoïde ; protection contre la dengue.', 'Rizières d\'Ubud, temples, plages, îles Gili, Komodo.'],
        ['Sri Lanka', 'Sri Lanka', 'Asie du Sud', 'CMB', 'Asia/Colombo', 1, 'LKR', 'Cingalais, tamoul', '11 h (escale)', 45, '222211111222', 'Deux moussons : la côte ouest et le sud sont idéaux de décembre à avril, l\'est de mai à septembre.', 'Passeport valide 6 mois + autorisation électronique (ETA) selon le régime en vigueur.', 'Hépatite A/B ; protection contre la dengue.', 'Sigiriya, Kandy, plantations de thé, safaris à Yala.'],
        ['Inde — Rajasthan', 'Inde', 'Asie du Sud', 'DEL', 'Asia/Kolkata', 1, 'INR', 'Hindi, anglais', '8 h 30 (direct)', 40, '222100000222', 'Très chaud d\'avril à juin, mousson ensuite ; idéal d\'octobre à mars.', 'Passeport valide 6 mois avec 2 pages vierges + e-visa.', 'Hépatite A/B, typhoïde ; eau en bouteille.', 'Taj Mahal, Jaipur, Udaipur, palais des maharajas.'],
        ['Japon', 'Japon', 'Asie de l\'Est', 'HND', 'Asia/Tokyo', 1, 'JPY', 'Japonais', '14 h (direct)', 100, '112210012211', 'Cerisiers en fleur fin mars-avril, érables rouges en novembre ; été chaud et humide.', 'Passeport valide pendant le séjour ; pas de visa pour un séjour touristique de moins de 90 jours.', 'Pas de vaccin obligatoire.', 'Tokyo, Kyoto, Alpes japonaises ; JR Pass, ryokans.'],
        // Amériques
        ['New York', 'États-Unis', 'Amérique du Nord', 'JFK', 'America/New_York', 1, 'USD', 'Anglais', '8 h 30', 150, '112222222122', 'Hivers froids et neigeux, étés chauds et humides.', 'Passeport électronique ou biométrique + ESTA obligatoire, à demander au moins 72 h avant le départ.', 'Soins très coûteux : une assurance médicale à plafond élevé est indispensable.', 'Manhattan, Broadway, musées ; Noël et automne très demandés.'],
        ['Ouest américain (road-trip)', 'États-Unis', 'Amérique du Nord', 'LAX', 'America/Los_Angeles', 1, 'USD', 'Anglais', '11 h 30', 130, '001222222210', 'Désertique et chaud en été (vallée de la Mort), neige possible en altitude en hiver.', 'Passeport électronique ou biométrique + ESTA obligatoire.', 'Une assurance médicale à plafond élevé est indispensable.', 'Grand Canyon, Las Vegas, parcs nationaux, route 66, Californie.'],
        ['Floride (Miami, Orlando)', 'États-Unis', 'Amérique du Nord', 'MIA', 'America/New_York', 1, 'USD', 'Anglais', '10 h', 130, '222221111122', 'Subtropical ; ouragans possibles de juin à novembre.', 'Passeport électronique ou biométrique + ESTA obligatoire.', 'Une assurance médicale à plafond élevé est indispensable.', 'Parcs d\'attractions d\'Orlando, Keys, Miami Beach ; départs en croisière Caraïbes.'],
        ['Québec & Canada de l\'Est', 'Canada', 'Amérique du Nord', 'YUL', 'America/Toronto', 1, 'CAD', 'Français, anglais', '7 h 30', 110, '110012222210', 'Hivers rigoureux (sports de neige), été indien flamboyant fin septembre-octobre.', 'Passeport + autorisation de voyage électronique (AVE) obligatoire en avion.', 'Une assurance médicale est indispensable.', 'Montréal, Québec, Charlevoix, baleines de Tadoussac.'],
        ['Mexique — Yucatán (Riviera Maya)', 'Mexique', 'Amérique centrale', 'CUN', 'America/Cancun', 1, 'MXN', 'Espagnol', '11 h', 70, '222221111122', 'Saison sèche de décembre à avril ; ouragans et sargasses possibles en été.', 'Passeport valide pendant le séjour ; formulaire migratoire numérique ; pas de visa.', 'Protection anti-moustiques (dengue).', 'Cenotes, Chichén Itzá, Tulum, Playa del Carmen.'],
        ['République dominicaine', 'Rép. dominicaine', 'Caraïbes', 'PUJ', 'America/Santo_Domingo', 1, 'DOP', 'Espagnol', '9 h', 60, '222221111122', 'Tropical ; saison des cyclones d\'août à octobre.', 'Passeport valide pendant le séjour + formulaire électronique d\'entrée et de sortie (e-Ticket).', 'Protection anti-moustiques.', 'Tout inclus à Punta Cana, Samaná, baleines de janvier à mars.'],
        ['Cuba', 'Cuba', 'Caraïbes', 'HAV', 'America/Havana', 1, 'CUP', 'Espagnol', '10 h', 50, '222221111122', 'Tropical ; saison sèche de novembre à avril.', 'Passeport valide 6 mois + carte de tourisme (visa) + formulaire d\'entrée en ligne ; assurance médicale obligatoire.', 'Protection anti-moustiques ; emporter les médicaments personnels.', 'La Havane, Trinidad, Viñales ; situation économique à suivre.'],
        ['Costa Rica', 'Costa Rica', 'Amérique centrale', 'SJO', 'America/Costa_Rica', 1, 'CRC', 'Espagnol', '11 h 30', 80, '222211111122', 'Saison sèche de décembre à avril ; côte caraïbe au calendrier différent.', 'Passeport valide pendant le séjour + billet retour ; pas de visa.', 'Protection anti-moustiques.', 'Volcans, forêts de nuages, faune, deux océans ; écotourisme.'],
        ['Pérou', 'Pérou', 'Amérique du Sud', 'LIM', 'America/Lima', 1, 'PEN', 'Espagnol, quechua', '12 h', 60, '000122222100', 'Andes : saison sèche de mai à octobre ; le chemin de l\'Inca est fermé en février.', 'Passeport valide 6 mois ; pas de visa pour un séjour touristique.', 'Risque de mal d\'altitude à Cuzco ; fièvre jaune recommandée pour l\'Amazonie.', 'Machu Picchu, Vallée sacrée, lac Titicaca, Amazonie.'],
        ['Argentine & Patagonie', 'Argentine', 'Amérique du Sud', 'EZE', 'America/Argentina/Buenos_Aires', 1, 'ARS', 'Espagnol', '13 h 30', 70, '221100000122', 'Patagonie praticable d\'octobre à avril ; Buenos Aires agréable au printemps et à l\'automne austral.', 'Passeport valide pendant le séjour ; pas de visa.', 'Fièvre jaune recommandée pour les chutes d\'Iguazú.', 'Buenos Aires, glacier Perito Moreno, Iguazú, route des vins.'],
        ['Brésil', 'Brésil', 'Amérique du Sud', 'GIG', 'America/Sao_Paulo', 1, 'BRL', 'Portugais', '11 h 30', 60, '222111122222', 'Tropical au nord et à Rio, tempéré au sud ; carnaval en février ou mars.', 'Passeport valide 6 mois ; pas de visa pour un séjour touristique.', 'Fièvre jaune recommandée selon les régions ; protection contre la dengue.', 'Rio, Salvador de Bahia, Amazonie, Iguazú.'],
        // Océanie
        ['Australie', 'Australie', 'Océanie', 'SYD', 'Australia/Sydney', 1, 'AUD', 'Anglais', '22 h (escale)', 120, '221112222222', 'Hémisphère sud ; le nord tropical a une saison des pluies de décembre à mars.', 'Passeport + visa électronique eVisitor (gratuit) à demander avant le départ.', 'Assurance médicale indispensable.', 'Sydney, Grande Barrière de corail, Uluru, Great Ocean Road.'],
        ['Nouvelle-Zélande', 'Nouvelle-Zélande', 'Océanie', 'AKL', 'Pacific/Auckland', 1, 'NZD', 'Anglais, maori', '24 h (escale)', 120, '222110001122', 'Hémisphère sud : été de décembre à mars, ski de juin à septembre.', 'Passeport + NZeTA à demander avant le départ + taxe touristique (IVL).', 'Assurance médicale indispensable.', 'Fjords, volcans, glaciers ; road-trips en van.'],
    ];

    /** [nom, catégorie, site, notes] */
    private const SUPPLIERS = [
        // Compagnies aériennes
        ['Air France', 'aerien', 'airfrance.fr', 'Compagnie nationale, réseau mondial (hub de Paris-CDG), alliance SkyTeam.'],
        ['Transavia', 'aerien', 'transavia.com', 'Low-cost du groupe Air France-KLM, au départ d\'Orly et des régions.'],
        ['Corsair', 'aerien', 'flycorsair.com', 'Antilles, océan Indien, Afrique de l\'Ouest, Canada.'],
        ['Air Caraïbes', 'aerien', 'aircaraibes.com', 'Antilles, Guyane, Cuba, République dominicaine.'],
        ['French bee', 'aerien', 'frenchbee.com', 'Long-courrier low-cost : Réunion, Tahiti, États-Unis.'],
        ['Air Austral', 'aerien', 'air-austral.com', 'Réunion, Mayotte, océan Indien.'],
        ['Air Tahiti Nui', 'aerien', 'airtahitinui.com', 'Polynésie française (escale aux États-Unis ou vol direct selon la saison).'],
        ['Emirates', 'aerien', 'emirates.com', 'Hub de Dubaï : Asie, océan Indien, Océanie.'],
        ['Qatar Airways', 'aerien', 'qatarairways.com', 'Hub de Doha, alliance oneworld.'],
        ['Turkish Airlines', 'aerien', 'turkishairlines.com', 'Hub d\'Istanbul, très vaste réseau, Star Alliance.'],
        ['Lufthansa', 'aerien', 'lufthansa.com', 'Hubs de Francfort et Munich, Star Alliance.'],
        ['KLM', 'aerien', 'klm.fr', 'Hub d\'Amsterdam, groupe Air France-KLM.'],
        ['easyJet', 'aerien', 'easyjet.com', 'Low-cost européen.'],
        ['Royal Air Maroc', 'aerien', 'royalairmaroc.com', 'Maroc et Afrique via Casablanca.'],
        ['Air Canada', 'aerien', 'aircanada.com', 'Canada et Amériques, Star Alliance.'],
        ['Thai Airways', 'aerien', 'thaiairways.com', 'Thaïlande et Asie du Sud-Est.'],
        ['Singapore Airlines', 'aerien', 'singaporeair.com', 'Asie et Océanie via Singapour.'],
        // Croisières
        ['MSC Croisières', 'croisiere', 'msccroisieres.fr', 'Méditerranée, Europe du Nord, Caraïbes ; nombreux départs de Marseille.'],
        ['Costa Croisières', 'croisiere', 'costacroisieres.fr', 'Méditerranée et monde ; départs de Marseille et Savone.'],
        ['Royal Caribbean', 'croisiere', 'royalcaribbean.com', 'Méga-navires, familles, Caraïbes et Méditerranée.'],
        ['Celebrity Cruises', 'croisiere', 'celebritycruises.com', 'Croisières premium.'],
        ['Norwegian Cruise Line', 'croisiere', 'ncl.com', 'Formule « freestyle », sans horaires de repas fixes.'],
        ['Ponant', 'croisiere', 'ponant.com', 'Croisières d\'expédition et de luxe, à la française.'],
        ['CroisiEurope', 'croisiere', 'croisieurope.com', 'Croisières fluviales en Europe et dans le monde.'],
        ['Hurtigruten', 'croisiere', 'hurtigruten.com', 'Express côtier norvégien et croisières d\'expédition.'],
        // Tour-opérateurs
        ['Club Med', 'to', 'clubmed.fr', 'Villages tout compris haut de gamme, montagne et mer.'],
        ['TUI France', 'to', 'tui.fr', 'Clubs Marmara et TUI Collection, circuits.'],
        ['Fram', 'to', 'fram.fr', 'Clubs Framissima, circuits.'],
        ['Salaün Holidays', 'to', 'salaun-holidays.com', 'Circuits, séjours et croisières ; groupe qui détient aussi Jet tours.'],
        ['Jet tours', 'to', 'jettours.com', 'Circuits, autotours et séjours.'],
        ['Kuoni', 'to', 'kuoni.fr', 'Voyages sur mesure haut de gamme.'],
        ['Asia', 'to', 'asia.fr', 'Spécialiste de l\'Asie et de l\'Océanie.'],
        ['Exotismes', 'to', 'exotismes.fr', 'Long-courrier : océan Indien, Caraïbes, Asie.'],
        ['Héliades', 'to', 'heliades.fr', 'Spécialiste de la Grèce et de Chypre.'],
        ['Austral Lagons', 'to', 'australlagons.com', 'Spécialiste du Pacifique et de l\'océan Indien.'],
        // Hôtellerie et banques de lits
        ['Accor', 'hotel', 'all.accor.com', 'Sofitel, Novotel, Mercure, ibis… ; programme pour les agences de voyages.'],
        ['Marriott International', 'hotel', 'marriott.com', 'Marriott, Sheraton, Westin, Ritz-Carlton…'],
        ['Hilton', 'hotel', 'hilton.com', 'Hilton, Conrad, Waldorf Astoria, DoubleTree…'],
        ['IHG Hotels & Resorts', 'hotel', 'ihg.com', 'InterContinental, Crowne Plaza, Holiday Inn…'],
        ['Barceló Hotel Group', 'hotel', 'barcelo.com', 'Clubs et hôtels en Espagne, aux Caraïbes et au Mexique.'],
        ['Riu Hotels & Resorts', 'hotel', 'riu.com', 'Tout inclus : Caraïbes, Canaries, Cap-Vert.'],
        ['Iberostar', 'hotel', 'iberostar.com', 'Resorts en Espagne, aux Caraïbes et au Mexique.'],
        ['Meliá Hotels International', 'hotel', 'melia.com', 'Meliá, Paradisus, Sol…'],
        ['Hotelbeds', 'receptif', 'hotelbeds.com', 'Banque de lits B2B : hôtels, transferts, activités à tarifs nets.'],
        ['WebBeds', 'receptif', 'webbeds.com', 'Banque de lits B2B à tarifs nets.'],
        ['Expedia TAAP', 'receptif', 'expediataap.com', 'Programme Expedia pour les agents de voyages (commissions).'],
        // Ferroviaire
        ['SNCF Voyageurs', 'ferroviaire', 'sncf-connect.com', 'TGV INOUI, OUIGO, Intercités ; pré-acheminements.'],
        ['Eurostar', 'ferroviaire', 'eurostar.com', 'Londres, Bruxelles, Amsterdam.'],
        ['Trenitalia', 'ferroviaire', 'trenitalia.com', 'Trains en Italie et Frecciarossa Paris-Milan.'],
        ['Renfe', 'ferroviaire', 'renfe.com', 'Trains espagnols, dont Paris-Barcelone.'],
        // Loueurs
        ['Hertz', 'loueur', 'hertz.fr', 'Location de véhicules dans le monde entier.'],
        ['Avis', 'loueur', 'avis.fr', 'Location de véhicules.'],
        ['Europcar', 'loueur', 'europcar.fr', 'Location de véhicules.'],
        ['Sixt', 'loueur', 'sixt.fr', 'Location de véhicules, gamme premium.'],
        ['Auto Escape', 'loueur', 'autoescape.com', 'Courtier en location de voitures.'],
        // Assurances
        ['Allianz Travel', 'assurance', 'allianz-travel.fr', 'Assurances annulation, multirisques, assistance.'],
        ['Chapka', 'assurance', 'chapkadirect.fr', 'Assurances voyage, longs séjours, PVT.'],
        ['AVA', 'assurance', 'ava.fr', 'Assurances voyage pour les professionnels du tourisme.'],
        // Transferts et activités
        ['Welcome Pickups', 'transfert', 'welcomepickups.com', 'Transferts privés depuis l\'aéroport avec accueil.'],
        ['Hoppa', 'transfert', 'hoppa.com', 'Transferts partagés et privés.'],
        ['GetYourGuide', 'activite', 'getyourguide.fr', 'Excursions et billets d\'entrée ; programme de partenariat.'],
        ['Viator', 'activite', 'viator.com', 'Excursions (groupe Tripadvisor).'],
        ['Civitatis', 'activite', 'civitatis.com', 'Visites guidées en français.'],
    ];

    public static function destinations(): array
    {
        return array_map(fn($d) => [
            'nom' => $d[0], 'pays' => $d[1], 'region' => $d[2], 'aeroport' => $d[3], 'fuseau' => $d[4], 'hors_ue' => $d[5],
            'devise' => $d[6], 'langue' => $d[7], 'vol_duree' => $d[8], 'budget_jour' => $d[9], 'mois_ideaux' => $d[10],
            'climat' => $d[11], 'formalites' => $d[12] . self::VERIF, 'sante' => $d[13], 'description' => $d[14],
        ], self::DESTINATIONS);
    }

    public static function suppliers(): array
    {
        return array_map(fn($s) => [
            'nom' => $s[0], 'type' => $s[1], 'site' => $s[2],
            'notes' => $s[3] . ' Commission et conditions selon votre contrat ou votre réseau.',
        ], self::SUPPLIERS);
    }

    public static function forEntity(string $entity): array
    {
        return match ($entity) {
            'destinations' => self::destinations(),
            'suppliers' => self::suppliers(),
            default => throw new NotFoundException('Catalogue inconnu'),
        };
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '’' => "'"]);
        return preg_replace('/[^a-z0-9]+/', ' ', $s);
    }

    /** Recherche d'une destination du catalogue par nom ou pays (pré-remplissage hors ligne). */
    public static function findDestination(string $q): ?array
    {
        $n = trim(self::normalize($q));
        if ($n === '') {
            return null;
        }
        $best = null;
        foreach (self::destinations() as $d) {
            $names = array_map(fn($x) => trim(self::normalize($x)), [$d['nom'], $d['pays']]);
            if ($names[0] === $n) {
                return $d;
            }
            if (!$best && (str_contains($names[0], $n) || ($n !== '' && str_contains($n, $names[0])) || $names[1] === $n)) {
                $best = $d;
            }
        }
        return $best;
    }
}
