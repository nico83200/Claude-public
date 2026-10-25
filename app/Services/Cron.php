<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Tâche planifiée (toutes les 15 min recommandé sur Hostinger) :
 * relance des transmissions Printful en échec, rattrapage des expéditions,
 * annulation des paniers abandonnés, synchronisation newsletter, nettoyage (dont photos et portraits IA
 * non commandés après 30 jours).
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
        $step('Relances de paniers envoyées', static fn (): string => (string) OrderService::sendRecoveries());
        $step('Newsletter synchronisée vers Brevo', static fn (): string => (string) Newsletter::syncPending());

        $step('Portraits IA', static function (): string {
            if (!AiPortrait::enabled()) {
                return 'non installé';
            }
            // Agrandissements terminés chez Replicate à récupérer (fichiers conservés environ 1 h)
            $rows = Database::all("SELECT * FROM ai_portraits WHERE status = 'ready' AND master_prediction_id IS NOT NULL
                AND master_file IS NULL AND created_at >= ? ORDER BY id DESC LIMIT 20", [date('Y-m-d H:i:s', time() - 3 * 3600)]);
            foreach ($rows as $row) {
                AiPortrait::refresh($row);
            }
            return AiPortrait::cleanup() . ' portrait(s) non commandé(s) supprimé(s), '
                . Upload::cleanupCustomerPhotos() . ' photo(s) cliente(s) expirée(s) supprimée(s)';
        });

        $step('Sauvegarde de la base', static fn (): string => DbBackup::weekly());

        $step('Nettoyage', static function (): string {
            $limit = date('Y-m-d H:i:s', time() - 30 * 86400);
            Database::query('DELETE FROM webhook_events WHERE created_at < ?', [$limit]);
            Database::query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
            return 'ok';
        });

        flock($lock, LOCK_UN);
        fclose($lock);
        @file_put_contents(STORAGE_PATH . '/cache/cron-last-run', date('c'));
        @unlink(STORAGE_PATH . '/cache/cron-alert-sent');
        return $report;
    }

    public const ALERT_AFTER = 3 * 3600;

    /** Date de la dernière exécution (null si jamais). */
    public static function lastRun(): ?int
    {
        $last = @file_get_contents(STORAGE_PATH . '/cache/cron-last-run');
        return $last ? (strtotime($last) ?: null) : null;
    }

    /**
     * Surveillance : si la tâche planifiée ne tourne plus depuis 3 h (ou n'a jamais tourné un jour
     * après l'installation), l'administratrice est prévenue par e-mail, au plus une fois par jour.
     * Appelée à la fin de chaque requête : quelques lectures de fichiers seulement.
     */
    public static function watchdog(): void
    {
        $last = self::lastRun();
        $reference = $last ?? (int) @filemtime(BASE_PATH . '/config/config.php');
        if (!$reference || $reference > time() - ($last ? self::ALERT_AFTER : 86400)) {
            return;
        }
        $flag = STORAGE_PATH . '/cache/cron-alert-sent';
        if (is_file($flag) && filemtime($flag) > time() - 86400) {
            return;
        }
        @touch($flag);
        Mailer::notifyAdmin('⚠️ La tâche planifiée ne tourne plus', 'admin_alert', [
            'title'   => 'La tâche planifiée (cron) ne s\'exécute plus',
            'message' => ($last ? 'Dernière exécution le ' . date('d/m/Y à H:i', $last) . '.' : 'Elle n\'a encore jamais été exécutée.')
                . ' Sans elle, les commandes en échec ne sont pas renvoyées à Printful, les demandes d\'avis et relances de paniers ne partent pas, et la sauvegarde hebdomadaire n\'est pas faite.'
                . ' Vérifiez la tâche dans hPanel › Avancé › Tâches Cron, ou lancez-la depuis Admin › Intégrations.',
            'order'   => null,
        ]);
        log_message('cron', 'Alerte envoyée : tâche planifiée inactive');
    }
}
