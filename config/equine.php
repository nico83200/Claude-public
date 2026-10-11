<?php

return [
    // Forçage de l'état d'installation (null = fichier verrou storage/app/installed.json)
    'installed' => env('APP_INSTALLED'),

    // Clé facultative exigée par l'installeur web (à définir avant l'envoi des fichiers)
    'install_key' => env('INSTALL_KEY'),

    'billing' => [
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
        'recovery_days' => (int) env('BILLING_RECOVERY_DAYS', 90),
    ],

    'horse_search' => [
        'wikidata' => (bool) env('HORSE_SEARCH_WIKIDATA', true),
        'brave_api_key' => env('BRAVE_SEARCH_API_KEY'),
        'timeout' => (int) env('HORSE_SEARCH_TIMEOUT', 8),
        'cache_minutes' => (int) env('HORSE_SEARCH_CACHE_MINUTES', 1440),
        'user_agent' => env('HORSE_SEARCH_USER_AGENT', 'JackCieBot/1.0'),
        'max_per_minute' => 10,
    ],

    'uploads' => [
        'max_kb' => (int) env('UPLOAD_MAX_KB', 10240),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
    ],

    /*
    | Rappels de suivi par défaut (modifiables cheval par cheval dans Santé › Rappels).
    | Clé = clé de la catégorie de soin. Maréchal et vaccination sont actifs par défaut.
    */
    'care_reminders' => [
        'farrier' => ['enabled' => true, 'interval_value' => 6, 'interval_unit' => 'weeks', 'notice_days' => 7],
        'vaccination' => ['enabled' => true, 'interval_value' => 12, 'interval_unit' => 'months', 'notice_days' => 14],
        'deworming' => ['enabled' => false, 'interval_value' => 4, 'interval_unit' => 'months', 'notice_days' => 7],
        'dentistry' => ['enabled' => false, 'interval_value' => 12, 'interval_unit' => 'months', 'notice_days' => 14],
        'osteopathy' => ['enabled' => false, 'interval_value' => 6, 'interval_unit' => 'months', 'notice_days' => 7],
    ],
];
