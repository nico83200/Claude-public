<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Backups\BackupService;
use App\Support\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Surveillance : prévient les super-administrateurs (notification + e-mail) quand le cron
 * de l'hébergement ne tourne plus, ou quand les erreurs augmentent anormalement.
 * Chaque alerte est envoyée au plus une fois toutes les 6 heures ; un message de retour
 * à la normale suit une alerte cron.
 *
 * Vérifiée par la tâche planifiée (toutes les 5 minutes) et, si le cron est arrêté,
 * à la fin des requêtes web (au plus toutes les 10 minutes).
 */
class SystemMonitor
{
    public const ERRORS_KEY = 'monitor:errors:';

    private const SILENCE_HOURS = 6;

    public static function enabled(): bool
    {
        return (bool) (ApplicationSetting::get('monitor_enabled') ?? true);
    }

    public static function cronMinutes(): int
    {
        return max(5, (int) (ApplicationSetting::get('monitor_cron_minutes') ?: 15));
    }

    public static function errorThreshold(): int
    {
        return max(1, (int) (ApplicationSetting::get('monitor_error_threshold') ?: 20));
    }

    /** Appelé à chaque erreur signalée : compteur par tranche de 10 minutes, sans requête en base. */
    public static function recordError(Throwable $e): void
    {
        try {
            $bucket = self::ERRORS_KEY.intdiv(now()->timestamp, 600);
            Cache::add($bucket, 0, now()->addHours(2));
            Cache::increment($bucket);
            $samples = Cache::get('monitor:samples', []);
            array_unshift($samples, ['at' => now()->format('d/m H:i'), 'message' => mb_substr(class_basename($e).' : '.$e->getMessage(), 0, 200)]);
            Cache::put('monitor:samples', array_slice($samples, 0, 5), now()->addHours(2));
        } catch (Throwable) {
            // la surveillance ne doit jamais provoquer d'erreur elle-même
        }
    }

    /** Erreurs signalées pendant la dernière heure. */
    public static function errorsLastHour(): int
    {
        $now = intdiv(now()->timestamp, 600);

        return (int) collect(range(0, 5))->sum(fn ($i) => (int) Cache::get(self::ERRORS_KEY.($now - $i), 0));
    }

    /** Minutes depuis le dernier passage du cron (null : jamais vu). */
    public static function cronSilentMinutes(): ?int
    {
        $last = NotificationDiagnostics::lastBeat();

        return $last ? intdiv(now()->timestamp - (int) $last, 60) : null;
    }

    /** @return string[] alertes envoyées */
    public function check(): array
    {
        if (! self::enabled() || ! Installation::isInstalled()) {
            return [];
        }
        $sent = [];
        $silent = self::cronSilentMinutes();
        if ($silent === null || $silent >= self::cronMinutes()) {
            $text = $silent === null
                ? 'Le cron de l\'hébergement n\'a encore jamais tourné : les rappels et notifications ne partent qu\'au passage des visiteurs.'
                : 'Le cron de l\'hébergement ne tourne plus depuis '.$this->duration($silent).' : rappels, récapitulatifs et notifications sont en retard.';
            if ($this->alert('cron', 'Cron arrêté', $text.' Vérifiez la tâche cron (cron.php) chez l\'hébergeur.')) {
                Cache::forever('monitor:cron_alerted', true);
                $sent[] = 'cron';
            }
        } elseif (Cache::pull('monitor:cron_alerted')) {
            $this->notifyAdmins('Cron rétabli', 'Le cron de l\'hébergement tourne de nouveau normalement.');
            Cache::forget('monitor:alert:cron');
            $sent[] = 'cron_ok';
        }

        $errors = self::errorsLastHour();
        if ($errors >= self::errorThreshold()) {
            $samples = collect(Cache::get('monitor:samples', []))->map(fn ($s) => '• '.$s['at'].' – '.$s['message'])->implode("\n");
            if ($this->alert('errors', 'Erreurs en hausse', $errors.' erreurs en une heure (seuil : '.self::errorThreshold().').'."\n\nDernières erreurs :\n".$samples."\n\nDétail dans Administration › Erreurs.")) {
                $sent[] = 'errors';
            }
        }

        // Sauvegarde Google Drive configurée mais aucune réussie depuis 48 heures (connexion retirée, Drive plein…).
        $backups = app(BackupService::class);
        if ($backups->ready()) {
            $since = ApplicationSetting::get('backup.connected_at');
            $last = BackupRun::where('kind', 'database')->where('status', 'done')->max('finished_at');
            $reference = $last ?? $since;
            if ($reference && Carbon::parse($reference)->lt(now()->subHours(48))
                && $this->alert('backup_stale', 'Sauvegardes en retard', ($last ? 'Aucune sauvegarde réussie sur Google Drive depuis le '.Carbon::parse($last)->format('d/m/Y à H:i').'.' : 'Google Drive est connecté mais aucune sauvegarde n\'a encore réussi.')
                    .' Vérifiez Administration › Sauvegardes (historique et erreurs).')) {
                $sent[] = 'backup';
            }
        }

        return $sent;
    }

    /** Relais quand le cron est arrêté : vérification depuis les requêtes web, au plus toutes les 10 minutes. */
    public static function checkFromWeb(): void
    {
        if (! self::enabled() || ! Cache::add('monitor:web-check', true, now()->addMinutes(10))) {
            return;
        }
        rescue(fn () => app(self::class)->check(), null, true);
    }

    /**
     * ElevenLabs a refusé une demande faute de crédits : les super-administrateurs sont prévenus
     * (notification + e-mail), au plus une fois toutes les 6 heures.
     */
    public static function voiceCreditsExhausted(string $detail): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return (bool) rescue(fn () => app(self::class)->alert('elevenlabs_credits', 'Crédits ElevenLabs épuisés',
            'ElevenLabs refuse les demandes faute de crédits : la dictée et la lecture vocale de l\'assistant sont indisponibles pour les utilisateurs (l\'assistant reste utilisable par écrit).'
            ."\n\nDétail : ".mb_substr($detail, 0, 400)
            ."\n\nRechargez le compte ou changez d'abonnement sur elevenlabs.io (Subscription), puis vérifiez avec « Tester la voix » dans Administration › Assistant IA."), false, true);
    }

    private function alert(string $key, string $title, string $body): bool
    {
        if (! Cache::add('monitor:alert:'.$key, true, now()->addHours(self::SILENCE_HOURS))) {
            return false;
        }

        return $this->notifyAdmins($title, $body) > 0;
    }

    private function notifyAdmins(string $title, string $body): int
    {
        $admins = User::where('is_super_admin', true)->whereNull('suspended_at')->get();
        foreach ($admins as $admin) {
            // Envoi immédiat : une alerte « cron arrêté » ne peut pas attendre la file, traitée par ce même cron.
            $admin->notifyNow(new AppNotification('system_alert', 'Jack&Cie · '.$title, $body, route('admin.notifications.index')));
        }

        return $admins->count();
    }

    private function duration(int $minutes): string
    {
        return $minutes < 120 ? $minutes.' minutes' : intdiv($minutes, 60).' heures';
    }
}
