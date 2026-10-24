<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Installer;

/**
 * Tâche planifiée (toutes les 15 min recommandé sur Hostinger) :
 * relance des transmissions Printful en échec, rattrapage des expéditions,
 * annulation des paniers abandonnés, synchronisation newsletter, nettoyage.
 */
final class Cron
{
    public static function run(): array
    {
        $lock = fopen(STORAGE_PATH . '/cache/cron.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['Une exécution est déjà en cours.'];
        }
        $report = ['Cron ' . date('d/m/Y H:i:s')];

        $step = static function (string $label, callable $fn) use (&$report): void {
            try {
                $report[] = $label . ' : ' . $fn();
            } catch (\Throwable $e) {
                $report[] = $label . ' : ERREUR ' . $e->getMessage();
                log_message('cron', $label, ['error' => $e->getMessage()]);
            }
        };

        $step('Relance des transmissions Printful', static function (): string {
            $orders = Database::all("SELECT id FROM orders WHERE status IN ('paid','in_production') AND fulfillment_status IN ('pending','error') AND fulfillment_attempts < 5 ORDER BY id LIMIT 20");
            $ok = 0;
            foreach ($orders as $o) {
                $ok += OrderService::fulfill((int) $o['id']) ? 1 : 0;
            }
            return $ok . '/' . count($orders) . ' transmise(s)';
        });

        $step('Synchronisation des statuts Printful', static function (): string {
            if (!Printful::configured()) {
                return 'Printful non configuré';
            }
            $orders = Database::all("SELECT * FROM orders WHERE status = 'in_production' AND fulfillment_status IN ('draft','submitted') AND printful_order_id IS NOT NULL ORDER BY updated_at LIMIT 25");
            foreach ($orders as $order) {
                OrderService::syncFromPrintful($order);
            }
            return count($orders) . ' commande(s) vérifiée(s)';
        });

        $step('Demandes d\'avis envoyées', static fn (): string => (string) Reviews::sendRequests());
        $step('Paniers abandonnés annulés', static fn (): string => (string) OrderService::cancelAbandoned());
        $step('Newsletter synchronisée vers Brevo', static fn (): string => (string) Newsletter::syncPending());

        $step('Copie de secours de la configuration', static function (): string {
            Installer::backupConfig(Database::pdo(), require CONFIG_FILE);
            return 'ok';
        });

        $step('Nettoyage', static function (): string {
            $limit = date('Y-m-d H:i:s', time() - 30 * 86400);
            Database::query('DELETE FROM webhook_events WHERE created_at < ?', [$limit]);
            Database::query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
            return 'ok';
        });

        flock($lock, LOCK_UN);
        fclose($lock);
        @file_put_contents(STORAGE_PATH . '/cache/cron-last-run', date('c'));
        return $report;
    }
}
