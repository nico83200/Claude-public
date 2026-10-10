<?php
// Copiez ce fichier en config.php (ou utilisez install.php qui le génère pour vous).
return [
    'db' => [
        // 'sqlite' (recommandé : aucun réglage) ou 'mysql'
        'driver' => 'sqlite',
        'path' => __DIR__ . '/data/voyage.sqlite',
        // Pour MySQL / MariaDB :
        // 'driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'name' => 'ma_base', 'user' => 'mon_user', 'pass' => '••••',
    ],
    // Assistant IA (optionnel) : import de captures / PDF et suggestions. Clé à créer sur https://console.anthropic.com
    'ai' => [
        'api_key' => '',
        'model' => 'claude-opus-5-5',
        'effort_extract' => 'low',     // lecture de documents : rapide
        'effort_suggest' => 'medium',  // relecture du voyage : plus de réflexion
    ],
    'timezone' => 'Europe/Paris',
    'debug' => false,
];
