<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance' => 300,
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Assistant vocal : compréhension des consignes (Claude) et transcription (ElevenLabs).
    // Clés modifiables dans Administration › Intégrations (prioritaires sur le .env).
    // Notifications sur le téléphone : seuls les services de notification des navigateurs sont acceptés
    // comme destinataires (protection contre l'envoi de requêtes vers des adresses arbitraires).
    'webpush' => [
        'allowed_hosts' => array_filter(explode(',', (string) env('WEBPUSH_ALLOWED_HOSTS', 'fcm.googleapis.com,jmt17.google.com,*.push.services.mozilla.com,*.notify.windows.com,*.push.apple.com,web.push.apple.com,*.wns.windows.com'))),
        'allow_http' => (bool) env('WEBPUSH_ALLOW_HTTP', false),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL'),
    ],
    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        'stt_model' => env('ELEVENLABS_STT_MODEL', 'scribe_v2'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
    ],

    // Connexion avec Google (OpenID Connect) et Apple (Sign in with Apple).
    // Réglables aussi dans Administration › Intégrations (prioritaire, secrets chiffrés).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'), // identifiant du « Services ID »
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'), // contenu du fichier .p8
    ],

];
