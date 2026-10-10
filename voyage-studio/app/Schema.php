<?php
declare(strict_types=1);

/**
 * Source unique de vérité du modèle de données.
 *
 * Sert à : générer/migrer les tables (SQLite & MySQL), valider les saisies côté API,
 * et construire dynamiquement les formulaires côté front (exposé via api.php?r=schema).
 *
 * Types de champ : string, text, email, int, float, bool, date, time, enum, fk
 * Options :
 *   label     libellé affiché
 *   required  champ obligatoire
 *   options   valeurs autorisées (enum) : [valeur => libellé]
 *   ref       entité référencée (fk)
 *   list      affiché dans les tableaux de liste
 *   search    inclus dans la recherche plein texte
 *   section   regroupement dans le formulaire
 *   wide      occupe toute la largeur du formulaire
 *   types     (prestations) types de prestation pour lesquels le champ est pertinent
 *   hidden    non affiché dans les formulaires (champ calculé / technique)
 *   help      aide contextuelle
 *   default   valeur par défaut
 */
final class Schema
{
    public const ITEM_TYPES = [
        'vol'         => 'Vol',
        'train'       => 'Train',
        'hebergement' => 'Hébergement',
        'croisiere'   => 'Croisière',
        'transfert'   => 'Transfert',
        'location'    => 'Location de véhicule',
        'activite'    => 'Excursion / activité',
        'assurance'   => 'Assurance',
        'visa'        => 'Visa / formalités',
        'frais'       => 'Frais divers',
        'autre'       => 'Autre',
    ];

    public const UNITS = [
        'forfait'        => 'Forfait (total)',
        'personne'       => 'Par personne',
        'nuit'           => 'Par nuit',
        'personne_nuit'  => 'Par personne et par nuit',
        'chambre_nuit'   => 'Par chambre / cabine et par nuit',
        'chambre'        => 'Par chambre / cabine (séjour)',
        'vehicule'       => 'Par véhicule',
        'jour'           => 'Par jour',
    ];

    public static function entities(): array
    {
        $types = self::ITEM_TYPES;
        $transport = ['vol', 'train', 'transfert', 'croisiere'];

        return [
            'clients' => [
                'label' => 'Clients', 'singular' => 'Client',
                'title' => ['nom', 'prenom'], 'order' => 'nom ASC, prenom ASC',
                'fields' => [
                    'civilite' => ['type' => 'enum', 'label' => 'Civilité', 'options' => ['' => '—', 'M.' => 'M.', 'Mme' => 'Mme'], 'section' => 'Identité'],
                    'nom' => ['type' => 'string', 'label' => 'Nom', 'required' => true, 'list' => true, 'search' => true, 'section' => 'Identité'],
                    'prenom' => ['type' => 'string', 'label' => 'Prénom', 'list' => true, 'search' => true, 'section' => 'Identité'],
                    'date_naissance' => ['type' => 'date', 'label' => 'Date de naissance', 'section' => 'Identité'],
                    'email' => ['type' => 'email', 'label' => 'E-mail', 'list' => true, 'search' => true, 'section' => 'Contact'],
                    'telephone' => ['type' => 'string', 'label' => 'Téléphone', 'list' => true, 'search' => true, 'section' => 'Contact'],
                    'adresse' => ['type' => 'text', 'label' => 'Adresse postale', 'section' => 'Contact', 'wide' => true],
                    'nationalite' => ['type' => 'string', 'label' => 'Nationalité', 'section' => 'Documents de voyage'],
                    'passeport_numero' => ['type' => 'string', 'label' => 'N° passeport', 'section' => 'Documents de voyage'],
                    'passeport_expiration' => ['type' => 'date', 'label' => 'Expiration passeport', 'section' => 'Documents de voyage', 'help' => 'Alerte si expiration < 6 mois après le retour'],
                    'cni_expiration' => ['type' => 'date', 'label' => 'Expiration CNI', 'section' => 'Documents de voyage'],
                    'fidelite' => ['type' => 'string', 'label' => 'Cartes de fidélité', 'section' => 'Préférences', 'help' => 'ex : AF FlyingBlue 123456'],
                    'preferences' => ['type' => 'text', 'label' => 'Préférences / budget habituel', 'section' => 'Préférences', 'wide' => true, 'search' => true],
                    'regime' => ['type' => 'string', 'label' => 'Régime alimentaire / mobilité', 'section' => 'Préférences'],
                    'source' => ['type' => 'string', 'label' => 'Provenance (bouche-à-oreille…)', 'section' => 'Préférences'],
                    'notes' => ['type' => 'text', 'label' => 'Notes internes', 'section' => 'Préférences', 'wide' => true],
                ],
            ],

            'suppliers' => [
                'label' => 'Fournisseurs', 'singular' => 'Fournisseur',
                'title' => ['nom'], 'order' => 'nom ASC',
                'fields' => [
                    'nom' => ['type' => 'string', 'label' => 'Nom', 'required' => true, 'list' => true, 'search' => true, 'section' => 'Fournisseur'],
                    'type' => ['type' => 'enum', 'label' => 'Catégorie', 'list' => true, 'section' => 'Fournisseur', 'options' => [
                        'aerien' => 'Compagnie aérienne / consolidateur', 'croisiere' => 'Compagnie de croisière', 'to' => 'Tour-opérateur',
                        'hotel' => 'Hôtel / chaîne', 'receptif' => 'Réceptif / DMC', 'transfert' => 'Transferts', 'loueur' => 'Loueur de véhicules',
                        'ferroviaire' => 'Ferroviaire', 'assurance' => 'Assurance', 'activite' => 'Activités / excursions', 'autre' => 'Autre',
                    ], 'default' => 'autre'],
                    'contact' => ['type' => 'string', 'label' => 'Interlocuteur', 'search' => true, 'section' => 'Contact'],
                    'email' => ['type' => 'email', 'label' => 'E-mail', 'list' => true, 'section' => 'Contact'],
                    'telephone' => ['type' => 'string', 'label' => 'Téléphone', 'section' => 'Contact'],
                    'site' => ['type' => 'string', 'label' => 'Site / extranet', 'section' => 'Contact'],
                    'identifiant' => ['type' => 'string', 'label' => 'Code agence / identifiant', 'section' => 'Contact'],
                    'commission_pct' => ['type' => 'float', 'label' => 'Commission habituelle (%)', 'list' => true, 'section' => 'Conditions', 'help' => 'Proposée par défaut sur les prestations commissionnées'],
                    'devise' => ['type' => 'string', 'label' => 'Devise de facturation', 'section' => 'Conditions', 'default' => 'EUR'],
                    'delai_paiement' => ['type' => 'string', 'label' => 'Conditions de paiement', 'section' => 'Conditions', 'help' => 'ex : 25 % à la réservation, solde J-45'],
                    'conditions_annulation' => ['type' => 'text', 'label' => "Conditions d'annulation", 'section' => 'Conditions', 'wide' => true],
                    'notes' => ['type' => 'text', 'label' => 'Notes internes', 'section' => 'Conditions', 'wide' => true, 'search' => true],
                ],
            ],

            'destinations' => [
                'label' => 'Destinations', 'singular' => 'Destination',
                'title' => ['nom'], 'order' => 'nom ASC',
                'fields' => [
                    'nom' => ['type' => 'string', 'label' => 'Destination', 'required' => true, 'list' => true, 'search' => true, 'section' => 'Généralités'],
                    'pays' => ['type' => 'string', 'label' => 'Pays', 'list' => true, 'search' => true, 'section' => 'Généralités'],
                    'region' => ['type' => 'string', 'label' => 'Zone / région', 'search' => true, 'section' => 'Généralités'],
                    'aeroport' => ['type' => 'string', 'label' => 'Aéroport principal (IATA)', 'section' => 'Généralités'],
                    'fuseau' => ['type' => 'string', 'label' => 'Fuseau horaire (IANA)', 'section' => 'Généralités', 'help' => 'ex : America/New_York'],
                    'hors_ue' => ['type' => 'bool', 'label' => 'Hors Union européenne', 'list' => true, 'section' => 'Généralités', 'help' => 'Marge exonérée de TVA (régime de la marge) — à valider avec votre expert-comptable', 'default' => 0],
                    'devise' => ['type' => 'string', 'label' => 'Devise locale (ISO)', 'section' => 'Pratique'],
                    'langue' => ['type' => 'string', 'label' => 'Langue(s)', 'section' => 'Pratique'],
                    'vol_duree' => ['type' => 'string', 'label' => 'Durée de vol depuis Paris', 'section' => 'Pratique'],
                    'budget_jour' => ['type' => 'float', 'label' => 'Budget sur place indicatif (€/pers/jour)', 'section' => 'Pratique'],
                    'mois_ideaux' => ['type' => 'string', 'label' => 'Meilleure saison', 'section' => 'Saisonnalité', 'widget' => 'months', 'help' => 'Codé 12 caractères : 2=idéal, 1=correct, 0=déconseillé'],
                    'climat' => ['type' => 'text', 'label' => 'Climat', 'section' => 'Saisonnalité', 'wide' => true],
                    'formalites' => ['type' => 'text', 'label' => 'Formalités (passeport, visa, ESTA…)', 'section' => 'Formalités & santé', 'wide' => true],
                    'sante' => ['type' => 'text', 'label' => 'Santé / vaccins', 'section' => 'Formalités & santé', 'wide' => true],
                    'description' => ['type' => 'text', 'label' => 'Description (reprise dans les devis)', 'section' => 'Contenu', 'wide' => true],
                    'notes' => ['type' => 'text', 'label' => 'Notes internes / bons plans', 'section' => 'Contenu', 'wide' => true, 'search' => true],
                ],
            ],

            'trips' => [
                'label' => 'Dossiers', 'singular' => 'Dossier',
                'title' => ['reference', 'titre'], 'order' => 'date_depart DESC, id DESC',
                'fields' => [
                    'reference' => ['type' => 'string', 'label' => 'Référence', 'list' => true, 'search' => true, 'section' => 'Dossier', 'help' => 'Générée automatiquement si vide'],
                    'titre' => ['type' => 'string', 'label' => 'Intitulé du voyage', 'required' => true, 'list' => true, 'search' => true, 'section' => 'Dossier'],
                    'client_id' => ['type' => 'fk', 'ref' => 'clients', 'label' => 'Client (payeur)', 'list' => true, 'section' => 'Dossier'],
                    'destination_id' => ['type' => 'fk', 'ref' => 'destinations', 'label' => 'Destination', 'list' => true, 'section' => 'Dossier'],
                    'statut' => ['type' => 'enum', 'label' => 'Statut', 'list' => true, 'section' => 'Dossier', 'default' => 'prospect', 'options' => [
                        'prospect' => 'Demande', 'devis' => 'Devis envoyé', 'option' => 'Option posée', 'confirme' => 'Confirmé',
                        'solde' => 'Soldé', 'termine' => 'Terminé', 'annule' => 'Annulé',
                    ]],
                    'date_depart' => ['type' => 'date', 'label' => 'Départ', 'list' => true, 'section' => 'Dates & participants'],
                    'date_retour' => ['type' => 'date', 'label' => 'Retour', 'section' => 'Dates & participants'],
                    'flexibilite' => ['type' => 'string', 'label' => 'Flexibilité des dates', 'section' => 'Dates & participants', 'help' => 'ex : ± 3 jours'],
                    'ville_depart' => ['type' => 'string', 'label' => 'Ville / aéroport de départ', 'section' => 'Dates & participants'],
                    'nb_adultes' => ['type' => 'int', 'label' => 'Adultes', 'section' => 'Dates & participants', 'default' => 2],
                    'nb_enfants' => ['type' => 'int', 'label' => 'Enfants (2-11 ans)', 'section' => 'Dates & participants', 'default' => 0],
                    'nb_bebes' => ['type' => 'int', 'label' => 'Bébés (< 2 ans)', 'section' => 'Dates & participants', 'default' => 0],
                    'budget' => ['type' => 'float', 'label' => 'Budget client total (€)', 'section' => 'Demande'],
                    'demande' => ['type' => 'text', 'label' => 'Demande / attentes du client', 'section' => 'Demande', 'wide' => true, 'search' => true],
                    'date_option_limite' => ['type' => 'date', 'label' => 'Validité du devis / option', 'section' => 'Demande'],
                    'selected_option_id' => ['type' => 'fk', 'ref' => 'options', 'label' => 'Variante retenue', 'hidden' => true],
                    'total_achat' => ['type' => 'float', 'label' => 'Total achat', 'hidden' => true],
                    'total_vente' => ['type' => 'float', 'label' => 'Total vente', 'hidden' => true],
                    'marge' => ['type' => 'float', 'label' => 'Marge brute', 'hidden' => true],
                    'notes' => ['type' => 'text', 'label' => 'Notes internes', 'section' => 'Demande', 'wide' => true],
                ],
                'children' => ['options' => 'trip_id', 'payments' => 'trip_id', 'travelers' => 'trip_id'],
            ],

            'travelers' => [
                'label' => 'Voyageurs', 'singular' => 'Voyageur', 'title' => ['client_id'], 'order' => 'id ASC',
                'fields' => [
                    'trip_id' => ['type' => 'fk', 'ref' => 'trips', 'label' => 'Dossier', 'required' => true, 'hidden' => true],
                    'client_id' => ['type' => 'fk', 'ref' => 'clients', 'label' => 'Voyageur', 'required' => true],
                ],
            ],

            'options' => [
                'label' => 'Variantes', 'singular' => 'Variante', 'title' => ['nom'], 'order' => 'ordre ASC, id ASC',
                'fields' => [
                    'trip_id' => ['type' => 'fk', 'ref' => 'trips', 'label' => 'Dossier', 'required' => true, 'hidden' => true],
                    'nom' => ['type' => 'string', 'label' => 'Nom de la variante', 'required' => true, 'section' => 'Variante', 'help' => 'ex : Option A — Croisière Méditerranée balcon'],
                    'resume' => ['type' => 'text', 'label' => 'Résumé (visible client)', 'section' => 'Variante', 'wide' => true],
                    'marge_mode' => ['type' => 'enum', 'label' => 'Mode de marge', 'section' => 'Tarification', 'options' => [
                        'coef' => 'Majoration % sur le prix d\'achat', 'marque' => 'Taux de marque % (sur prix de vente)',
                        'fixe_pers' => 'Montant fixe par personne', 'fixe_total' => 'Montant fixe sur le dossier',
                    ], 'default' => 'coef'],
                    'marge_valeur' => ['type' => 'float', 'label' => 'Valeur de marge', 'section' => 'Tarification', 'help' => 'Appliquée aux prestations achetées en net'],
                    'frais_dossier' => ['type' => 'float', 'label' => 'Frais de dossier (total €)', 'section' => 'Tarification'],
                    'frais_dossier_pers' => ['type' => 'float', 'label' => 'Frais de dossier (€/pers)', 'section' => 'Tarification'],
                    'arrondi' => ['type' => 'int', 'label' => 'Arrondir le prix/pers au multiple de', 'section' => 'Tarification', 'help' => '0 = pas d\'arrondi ; 5, 10, 50… arrondit au supérieur'],
                    'inclus' => ['type' => 'text', 'label' => 'Le prix comprend', 'section' => 'Contenu du devis', 'wide' => true],
                    'non_inclus' => ['type' => 'text', 'label' => 'Le prix ne comprend pas', 'section' => 'Contenu du devis', 'wide' => true],
                    'ordre' => ['type' => 'int', 'label' => 'Ordre', 'hidden' => true, 'default' => 0],
                ],
                'children' => ['items' => 'option_id', 'days' => 'option_id'],
            ],

            'items' => [
                'label' => 'Prestations', 'singular' => 'Prestation', 'title' => ['libelle'], 'order' => 'date_debut ASC, heure_debut ASC, ordre ASC, id ASC',
                'fields' => [
                    'option_id' => ['type' => 'fk', 'ref' => 'options', 'label' => 'Variante', 'required' => true, 'hidden' => true],
                    'type' => ['type' => 'enum', 'label' => 'Type', 'required' => true, 'options' => $types, 'section' => 'Prestation', 'default' => 'vol'],
                    'libelle' => ['type' => 'string', 'label' => 'Libellé', 'required' => true, 'section' => 'Prestation'],
                    'supplier_id' => ['type' => 'fk', 'ref' => 'suppliers', 'label' => 'Fournisseur', 'section' => 'Prestation'],
                    'description' => ['type' => 'text', 'label' => 'Description (visible client)', 'section' => 'Prestation', 'wide' => true],
                    'optionnel' => ['type' => 'bool', 'label' => 'Prestation optionnelle (hors forfait)', 'section' => 'Prestation', 'default' => 0],

                    'date_debut' => ['type' => 'date', 'label' => 'Date début / départ', 'section' => 'Dates & horaires'],
                    'heure_debut' => ['type' => 'time', 'label' => 'Heure départ (locale)', 'section' => 'Dates & horaires', 'types' => [...$transport, 'location', 'activite']],
                    'date_fin' => ['type' => 'date', 'label' => 'Date fin / arrivée', 'section' => 'Dates & horaires'],
                    'heure_fin' => ['type' => 'time', 'label' => 'Heure arrivée (locale)', 'section' => 'Dates & horaires', 'types' => [...$transport, 'location', 'activite']],
                    'lieu_depart' => ['type' => 'string', 'label' => 'Départ (IATA / port / lieu)', 'section' => 'Dates & horaires', 'types' => [...$transport, 'location']],
                    'lieu_arrivee' => ['type' => 'string', 'label' => 'Arrivée (IATA / port / lieu)', 'section' => 'Dates & horaires', 'types' => [...$transport, 'location']],
                    'tz_depart' => ['type' => 'string', 'label' => 'Fuseau départ', 'section' => 'Dates & horaires', 'types' => ['vol', 'train'], 'help' => 'Déduit du code IATA si connu'],
                    'tz_arrivee' => ['type' => 'string', 'label' => 'Fuseau arrivée', 'section' => 'Dates & horaires', 'types' => ['vol', 'train']],
                    'duree_min' => ['type' => 'int', 'label' => 'Durée (minutes)', 'section' => 'Dates & horaires', 'types' => [...$transport, 'activite'], 'help' => 'Calculée automatiquement pour les vols si horaires + fuseaux connus'],

                    'compagnie' => ['type' => 'string', 'label' => 'Compagnie / opérateur', 'section' => 'Détails', 'types' => ['vol', 'train', 'croisiere', 'transfert', 'location']],
                    'numero' => ['type' => 'string', 'label' => 'N° vol / train', 'section' => 'Détails', 'types' => ['vol', 'train']],
                    'classe' => ['type' => 'string', 'label' => 'Classe / tarif', 'section' => 'Détails', 'types' => ['vol', 'train']],
                    'escales' => ['type' => 'int', 'label' => "Nombre d'escales", 'section' => 'Détails', 'types' => ['vol', 'train'], 'default' => 0],
                    'bagages' => ['type' => 'string', 'label' => 'Bagages inclus', 'section' => 'Détails', 'types' => ['vol'], 'help' => 'ex : 1 × 23 kg + cabine'],
                    'navire' => ['type' => 'string', 'label' => 'Navire', 'section' => 'Détails', 'types' => ['croisiere']],
                    'categorie' => ['type' => 'string', 'label' => 'Catégorie (étoiles / cabine / véhicule)', 'section' => 'Détails', 'types' => ['hebergement', 'croisiere', 'location', 'transfert']],
                    'chambre' => ['type' => 'string', 'label' => 'Type de chambre / cabine', 'section' => 'Détails', 'types' => ['hebergement', 'croisiere']],
                    'pension' => ['type' => 'enum', 'label' => 'Formule', 'section' => 'Détails', 'types' => ['hebergement', 'croisiere'], 'options' => [
                        '' => '—', 'RO' => 'Logement seul', 'BB' => 'Petit-déjeuner', 'HB' => 'Demi-pension', 'FB' => 'Pension complète', 'AI' => 'Tout inclus',
                    ]],
                    'mode_transfert' => ['type' => 'enum', 'label' => 'Mode', 'section' => 'Détails', 'types' => ['transfert'], 'options' => ['' => '—', 'prive' => 'Privé', 'partage' => 'Partagé / navette', 'chauffeur' => 'Avec chauffeur-guide']],
                    'distance_km' => ['type' => 'float', 'label' => 'Distance (km)', 'section' => 'Détails', 'types' => ['transfert', 'location']],

                    'mode_tarif' => ['type' => 'enum', 'label' => "Mode d'achat", 'section' => 'Tarif', 'options' => [
                        'net' => 'Prix net (j\'applique ma marge)', 'commission' => 'Prix public commissionné',
                    ], 'default' => 'net'],
                    'unite' => ['type' => 'enum', 'label' => 'Unité de tarif', 'section' => 'Tarif', 'options' => self::UNITS, 'default' => 'personne'],
                    'quantite' => ['type' => 'float', 'label' => 'Quantité', 'section' => 'Tarif', 'help' => 'Vide = calculée (nb de voyageurs payants, chambres doubles…)'],
                    'prix_unitaire' => ['type' => 'float', 'label' => 'Prix unitaire', 'section' => 'Tarif', 'help' => 'Net ou public selon le mode d\'achat, hors taxes non commissionnables'],
                    'taxes' => ['type' => 'float', 'label' => 'Taxes / frais non commissionnables (total)', 'section' => 'Tarif', 'help' => 'Taxes aéroport, taxes portuaires, taxe de séjour… Refacturées au coût'],
                    'devise' => ['type' => 'string', 'label' => 'Devise', 'section' => 'Tarif', 'default' => 'EUR'],
                    'commission_pct' => ['type' => 'float', 'label' => 'Commission (%)', 'section' => 'Tarif'],
                    'marge_pct' => ['type' => 'float', 'label' => 'Majoration spécifique (%)', 'section' => 'Tarif', 'help' => 'Remplace la marge de la variante pour cette prestation'],
                    'prix_vente_force' => ['type' => 'float', 'label' => 'Prix de vente imposé (total, devise de base)', 'section' => 'Tarif'],

                    'statut' => ['type' => 'enum', 'label' => 'Statut réservation', 'section' => 'Réservation', 'default' => 'a_demander', 'options' => [
                        'a_demander' => 'À demander', 'demande' => 'Demandé', 'option' => 'Sous option', 'confirme' => 'Confirmé', 'annule' => 'Annulé',
                    ]],
                    'ref_reservation' => ['type' => 'string', 'label' => 'Réf. réservation / PNR', 'section' => 'Réservation'],
                    'date_limite_option' => ['type' => 'date', 'label' => "Date limite d'option fournisseur", 'section' => 'Réservation'],
                    'notes' => ['type' => 'text', 'label' => 'Notes internes', 'section' => 'Réservation', 'wide' => true],
                    'ordre' => ['type' => 'int', 'label' => 'Ordre', 'hidden' => true, 'default' => 0],
                ],
            ],

            'days' => [
                'label' => 'Programme', 'singular' => 'Journée', 'title' => ['titre'], 'order' => 'jour ASC, id ASC',
                'fields' => [
                    'option_id' => ['type' => 'fk', 'ref' => 'options', 'label' => 'Variante', 'required' => true, 'hidden' => true],
                    'jour' => ['type' => 'int', 'label' => 'Jour n°', 'required' => true],
                    'titre' => ['type' => 'string', 'label' => 'Titre', 'required' => true, 'help' => 'ex : Paris ✈ Bangkok'],
                    'lieu' => ['type' => 'string', 'label' => 'Lieu de nuit'],
                    'repas' => ['type' => 'string', 'label' => 'Repas inclus', 'help' => 'ex : P / D / —'],
                    'description' => ['type' => 'text', 'label' => 'Description', 'wide' => true],
                ],
            ],

            'payments' => [
                'label' => 'Échéances', 'singular' => 'Échéance', 'title' => ['libelle'], 'order' => 'date_echeance ASC, id ASC',
                'fields' => [
                    'trip_id' => ['type' => 'fk', 'ref' => 'trips', 'label' => 'Dossier', 'required' => true, 'hidden' => true],
                    'sens' => ['type' => 'enum', 'label' => 'Sens', 'options' => ['client' => 'Encaissement client', 'fournisseur' => 'Paiement fournisseur'], 'default' => 'client'],
                    'supplier_id' => ['type' => 'fk', 'ref' => 'suppliers', 'label' => 'Fournisseur'],
                    'libelle' => ['type' => 'string', 'label' => 'Libellé', 'required' => true],
                    'montant' => ['type' => 'float', 'label' => 'Montant (€)', 'required' => true],
                    'date_echeance' => ['type' => 'date', 'label' => 'Échéance'],
                    'paye' => ['type' => 'bool', 'label' => 'Réglé', 'default' => 0],
                    'date_paiement' => ['type' => 'date', 'label' => 'Date de règlement'],
                    'mode' => ['type' => 'enum', 'label' => 'Mode', 'options' => [
                        '' => '—', 'virement' => 'Virement', 'cb' => 'Carte bancaire', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'ancv' => 'Chèques-Vacances', 'avoir' => 'Avoir',
                    ]],
                ],
            ],

            'currencies' => [
                'label' => 'Devises', 'singular' => 'Devise', 'title' => ['code'], 'order' => 'code ASC',
                'fields' => [
                    'code' => ['type' => 'string', 'label' => 'Code ISO', 'required' => true, 'list' => true],
                    'taux' => ['type' => 'float', 'label' => 'Taux (1 EUR = x)', 'required' => true, 'list' => true],
                    'maj' => ['type' => 'string', 'label' => 'Mise à jour', 'list' => true],
                ],
            ],
        ];
    }

    /** Tables techniques (non exposées au CRUD générique). */
    public static function systemTables(): array
    {
        return [
            'users' => [
                'email' => ['type' => 'email', 'required' => true],
                'nom' => ['type' => 'string'],
                'password_hash' => ['type' => 'string', 'required' => true],
            ],
            'settings' => [
                'k' => ['type' => 'string', 'required' => true],
                'v' => ['type' => 'text'],
            ],
            'login_attempts' => [
                'ip' => ['type' => 'string'],
                'ts' => ['type' => 'int'],
            ],
        ];
    }

    public static function entity(string $name): ?array
    {
        return self::entities()[$name] ?? null;
    }

    /** Version publique (sans détails internes) pour le front. */
    public static function publicSchema(): array
    {
        return ['entities' => self::entities(), 'itemTypes' => self::ITEM_TYPES, 'units' => self::UNITS];
    }
}
