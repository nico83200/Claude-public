<?php
/**
 * Configuration de référence.
 *
 * Ne modifiez pas ce fichier : l'installateur (/install) génère config/config.php
 * qui surcharge ces valeurs. Les secrets (clés API, mots de passe) ne doivent
 * jamais être commités.
 */
return [
    'app' => [
        'name'     => 'Crinière & Co',
        'url'      => 'http://localhost:8000',   // URL publique, sans slash final
        'env'      => 'production',              // production | local
        'debug'    => false,
        'timezone' => 'Europe/Paris',
        'key'      => '',                         // clé aléatoire générée à l'installation
    ],

    'db' => [
        'driver'   => 'mysql',                    // mysql (Hostinger) | sqlite (dev local)
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => '',
        'user'     => '',
        'pass'     => '',
        'sqlite'   => BASE_PATH . '/storage/database.sqlite',
    ],

    // https://dashboard.stripe.com/apikeys
    'stripe' => [
        'secret_key'      => '',                  // sk_live_... / sk_test_...
        'publishable_key' => '',                  // pk_live_... / pk_test_...
        'webhook_secret'  => '',                  // whsec_...
        'currency'        => 'eur',
        'allowed_countries' => ['FR', 'BE', 'CH', 'LU', 'MC', 'DE', 'ES', 'IT', 'NL', 'PT', 'AT', 'IE'],
    ],

    // https://developers.printful.com/tokens
    'printful' => [
        'api_token'     => '',                    // jeton privé (Bearer)
        'store_id'      => '',                    // requis si le jeton couvre plusieurs boutiques
        'auto_confirm'  => false,                 // true = la commande part en production immédiatement
        'webhook_token' => '',                    // secret inclus dans l'URL du webhook Printful
    ],

    // Hostinger : smtp.hostinger.com, port 465 (ssl) ou 587 (tls)
    'mail' => [
        'transport'   => 'smtp',                  // smtp | mail | log
        'host'        => 'smtp.hostinger.com',
        'port'        => 465,
        'encryption'  => 'ssl',                   // ssl | tls | none
        'username'    => '',
        'password'    => '',
        'from_email'  => '',
        'from_name'   => 'Crinière & Co',
        'admin_email' => '',                      // reçoit les alertes (nouvelles commandes, erreurs)
    ],

    // Optionnel : synchronisation newsletter (https://app.brevo.com/settings/keys/api)
    'brevo' => [
        'api_key' => '',
        'list_id' => 0,
    ],

    // Optionnel : chargés uniquement après consentement cookies
    'analytics' => [
        'ga4_id'        => '',                    // G-XXXXXXX
        'meta_pixel_id' => '',
    ],

    'cron' => [
        'token' => '',                            // protège /tache-planifiee/{token}
    ],
];
