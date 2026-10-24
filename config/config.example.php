<?php
/**
 * Configuration de référence.
 *
 * Ne modifiez pas ce fichier : l'installateur génère config/config.php (URL et accès à la base).
 * Les clés Stripe, Printful, e-mail, Brevo, Analytics et les jetons des webhooks sont enregistrés
 * en base de données et se modifient dans Administration › Intégrations : les valeurs ci-dessous
 * ne servent que de valeurs par défaut.
 */
return [
    'app' => [
        'name'     => 'Crinière Club',
        'url'      => '',                        // URL publique sans slash final (renseignée par l'installateur)
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
        'from_name'   => 'Crinière Club',
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

    // Portrait redessiné par IA (Administration › Intégrations › Portrait IA)
    'ai' => [
        'provider'          => 'demo',            // demo (effet croquis local, sans IA) | openai | replicate
        'openai_key'        => '',                // https://platform.openai.com/api-keys
        'openai_model'      => 'gpt-image-1.5',
        'openai_quality'    => 'medium',          // low | medium | high
        'replicate_token'   => '',                // https://replicate.com/account/api-tokens
        'replicate_model'   => 'black-forest-labs/flux-kontext-pro',
        'upscale'           => false,
        'upscale_model'     => 'recraft-ai/recraft-crisp-upscale',
        'daily_per_visitor' => 3,
        'daily_budget'      => 60,
    ],
];
