<?php
declare(strict_types=1);

/**
 * Tâches planifiées (e-mails, rappels, retards, sauvegarde quotidienne, nettoyage).
 *
 *   Ligne de commande : php cron.php            (ajoutez --force pour tout exécuter)
 *   Par le web        : https://votre-site/cron.php?key=CLÉ   (clé affichée dans Paramètres)
 *
 * Conseil : programmer un appel toutes les 5 à 15 minutes chez l'hébergeur.
 */
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
