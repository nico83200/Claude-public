<?php

return [
    'billing' => [
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
        'recovery_days' => (int) env('BILLING_RECOVERY_DAYS', 90),
    ],

    'horse_search' => [
        'wikidata' => (bool) env('HORSE_SEARCH_WIKIDATA', true),
        'brave_api_key' => env('BRAVE_SEARCH_API_KEY'),
        'timeout' => (int) env('HORSE_SEARCH_TIMEOUT', 8),
        'cache_minutes' => (int) env('HORSE_SEARCH_CACHE_MINUTES', 1440),
        'user_agent' => env('HORSE_SEARCH_USER_AGENT', 'EquilibreBot/1.0'),
        'max_per_minute' => 10,
    ],

    'offline' => [
        'ttl_hours' => (int) env('OFFLINE_DATA_TTL_HOURS', 168),
        'max_horses' => (int) env('OFFLINE_MAX_HORSES', 10),
        'recent_sessions' => 20,
    ],

    'uploads' => [
        'max_kb' => (int) env('UPLOAD_MAX_KB', 10240),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
    ],
];
