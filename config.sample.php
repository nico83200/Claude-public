<?php
/**
 * Copier ce fichier en config.php puis renseigner les accès.
 */
return [
    // Base de données MySQL / MariaDB (recommandé en production)
    'db' => [
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'commandes_centres',
        'user'    => 'utilisateur_sql',
        'pass'    => 'mot_de_passe_sql',
    ],
    // Alternative sans serveur SQL (tests / petite structure) :
    // 'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/app.sqlite'],

    // Nom affiché de l'application
    'app_name' => 'Centriva',

    // Fuseau horaire
    'timezone' => 'Europe/Paris',

    // Clé API Anthropic (Claude) pour la recherche assistée par IA.
    // Laisser vide pour n'utiliser que la recherche intelligente locale.
    // La variable d'environnement ANTHROPIC_API_KEY est aussi prise en compte.
    'anthropic_api_key' => '',

    // Assistance de l'éditeur (bulle d'aide, page Assistance) — valeurs par défaut : NLapps
    // 'support_editor' => 'NLapps', 'support_site' => 'https://nlapps.fr', 'support_email' => 'contact@nlapps.fr',
    // 'support_phone' => '+33 6 52 43 67 47',
    // Conversation en direct avec NLapps (bouton « Parler à un conseiller ») : clé fournie par NLapps
    // 'support_hub_url' => 'https://nlapps.fr/assistance/api.php', 'support_hub_key' => 'nlh_…',

    // Taille max des photos produits (octets)
    'max_upload' => 4 * 1024 * 1024,
];
