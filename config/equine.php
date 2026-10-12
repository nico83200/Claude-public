<?php

return [
    // Relais du planificateur à chaque visite quand le cron de l'hébergement n'est pas en place.
    'scheduler_fallback' => (bool) env('SCHEDULER_FALLBACK', true),

    // Forçage de l'état d'installation (null = fichier verrou storage/app/installed.json)
    'installed' => env('APP_INSTALLED'),

    // Clé facultative exigée par l'installeur web (à définir avant l'envoi des fichiers)
    'install_key' => env('INSTALL_KEY'),

    'billing' => [
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
        'recovery_days' => (int) env('BILLING_RECOVERY_DAYS', 90),
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
