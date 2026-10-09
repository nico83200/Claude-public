<?php
declare(strict_types=1);

/**
 * Tâches planifiées (e-mails, rappels, retards, sauvegarde quotidienne, nettoyage).
 *
 *   Ligne de commande : php cron.php            (ajoutez --force pour tout exécuter ; tous les clients si plusieurs sont installés)
 *   Par le web        : https://votre-site/cron.php?key=CLÉ   (clé affichée dans Paramètres)
 *
 * Conseil : programmer un appel toutes les 5 à 15 minutes chez l'hébergeur.
 */
// Plusieurs clients installés : en ligne de commande, sans client précisé, on passe chacun d'eux en revue
if (PHP_SAPI === 'cli' && !getenv('CMD_INSTANCE') && !getenv('CMD_CONFIG') && is_file(__DIR__ . '/instances/registry.php')) {
    define('NL_CONSOLE', true);
    require __DIR__ . '/app/bootstrap.php';
    require APP . '/instances_admin.php';
    echo json_encode(['date' => date('Y-m-d H:i:s'), 'clients' => instances_cron_all(in_array('--force', $argv ?? [], true))], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
    exit;
}
require __DIR__ . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $key = (string)setting('cron_key', '');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('Clé invalide.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
$report = cron_run(in_array('--force', $argv ?? [], true) || isset($_GET['force']));
echo json_encode(['date' => now(), 'taches' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
