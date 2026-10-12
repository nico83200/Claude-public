<?php

namespace App\Services;

use App\Console\Commands\SendActivityReminders;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * État des notifications : planificateur (cron), file d'attente, appareils abonnés,
 * et raison pour laquelle un rappel quotidien est (ou n'est pas) parti.
 */
class NotificationDiagnostics
{
    public const CRON_KEY = 'scheduler.cron_heartbeat';

    public const FALLBACK_KEY = 'scheduler.fallback_run';

    /** Tâches quotidiennes rattrapées par le relais (sans doublon : chacune ne notifie qu'une fois). */
    private const DAILY = ['expenses:recurring' => '06:05', 'reminders:send' => '07:30', 'reminders:rhythm' => '08:50', 'reminders:daily-nudge' => '11:10'];

    /** Appelé chaque minute par le planificateur lancé par le cron du serveur. */
    public static function beat(): void
    {
        if (! app()->bound('scheduler.fallback')) {
            Cache::forever(self::CRON_KEY, now()->timestamp);
        }
    }

    public static function cronAlive(): bool
    {
        $last = Cache::get(self::CRON_KEY);

        return $last && now()->timestamp - (int) $last < 180;
    }

    /**
     * Relais quand le cron de l'hébergement n'est pas en place : à la fin d'une requête,
     * au plus une fois par minute, les tâches planifiées sont lancées (les rappels dus partent alors en retard
     * plutôt que jamais). Sans effet dès que le cron tourne.
     */
    public static function runFallbackIfNeeded(): void
    {
        if (self::cronAlive() || ! config('equine.scheduler_fallback', true)) {
            return;
        }
        $lock = Cache::lock('scheduler-fallback', 55);
        if (! $lock->get()) {
            return;
        }
        try {
            app()->instance('scheduler.fallback', true);
            Cache::forever(self::FALLBACK_KEY, now()->timestamp);
            Artisan::call('schedule:run');
            // Tâches du jour à heure fixe : sans cron, la minute exacte est presque toujours manquée.
            foreach (self::DAILY as $command => $time) {
                $key = 'scheduler.fallback.'.$command.'.'.today()->toDateString();
                if (now()->format('H:i') >= $time && ! Cache::has($key)) {
                    Cache::put($key, true, now()->endOfDay());
                    Artisan::call($command);
                }
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            app()->forgetInstance('scheduler.fallback');
        }
    }

    public function scheduler(): array
    {
        $cron = Cache::get(self::CRON_KEY);
        $fallback = Cache::get(self::FALLBACK_KEY);

        return [
            'cron_ok' => self::cronAlive(),
            'cron_last' => $cron ? Carbon::createFromTimestamp($cron, config('app.timezone')) : null,
            'fallback_last' => $fallback ? Carbon::createFromTimestamp($fallback, config('app.timezone')) : null,
            'cron_line' => '* * * * * cd '.base_path().' && '.self::phpCli().' artisan schedule:run >> /dev/null 2>&1',
            'cron_command' => self::phpCli().' '.base_path('cron.php'),
            'cron_file' => base_path('cron.php'),
            'cron_php_runs' => $this->cronPhpRuns(),
        ];
    }

    /** Derniers passages de cron.php (heure, type de PHP, résultat). */
    private function cronPhpRuns(): array
    {
        $file = storage_path('logs/cron.log');
        if (! is_file($file)) {
            return [];
        }
        $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -3);

        return array_reverse(array_values(array_filter(array_map(fn ($l) => json_decode($l, true), $lines))));
    }

    /** Chemin d'un PHP 8.3+ en ligne de commande sur ce serveur (le « php » par défaut du cron peut être plus ancien). */
    public static function phpCli(): string
    {
        foreach (['/opt/alt/php83/usr/bin/php', '/usr/bin/php8.3', '/opt/cpanel/ea-php83/root/usr/bin/php', '/usr/local/bin/php83',
            '/opt/alt/php84/usr/bin/php', '/usr/bin/php8.4', '/opt/cpanel/ea-php84/root/usr/bin/php', '/usr/local/bin/php84',
            '/opt/alt/php85/usr/bin/php', '/usr/bin/php8.5', '/opt/cpanel/ea-php85/root/usr/bin/php', '/usr/local/bin/php85'] as $bin) {
            if (@is_executable($bin)) {
                return $bin;
            }
        }

        return 'php';
    }

    public function queue(): array
    {
        $pending = rescue(fn () => DB::table('jobs')->count(), null, false);
        $oldest = rescue(fn () => DB::table('jobs')->min('created_at'), null, false);

        return [
            'pending' => $pending,
            'oldest' => $oldest ? Carbon::createFromTimestamp($oldest, config('app.timezone')) : null,
            'failed' => rescue(fn () => DB::table('failed_jobs')->count(), null, false),
        ];
    }

    /** Pourquoi le rappel quotidien est (ou non) parti aujourd'hui pour cet utilisateur. */
    public function dailyReminder(User $user): array
    {
        $at = $user->profile?->daily_reminder_at;
        if (! $at) {
            return ['state' => 'off', 'message' => 'Rappel quotidien désactivé.'];
        }
        $time = substr($at, 0, 5);
        $sent = Reminder::where(['user_id' => $user->id, 'remindable_type' => $user->getMorphClass(), 'remindable_id' => $user->id])
            ->whereDate('remind_at', today())->first();
        $devices = PushSubscription::where('user_id', $user->id)->count();
        $noDevice = $devices ? '' : ' Aucun téléphone n\'est abonné : il n\'apparaît que dans les notifications de l\'application.';
        if ($sent?->sent_at) {
            return ['state' => 'sent', 'message' => 'Envoyé aujourd\'hui à '.$sent->sent_at->format('H:i').'.'.$noDevice];
        }
        if (now()->format('H:i') < $time) {
            return ['state' => 'waiting', 'message' => 'Prévu aujourd\'hui à '.$time.'.'.$noDevice];
        }
        if (SendActivityReminders::wroteSomethingToday($user)) {
            return ['state' => 'skipped', 'message' => 'Pas envoyé aujourd\'hui : vous avez déjà noté quelque chose (séance, soin, observation, dépense ou assistant). C\'est voulu.'];
        }
        if (! app(HorseAccess::class)->accessibleQuery($user)->whereNull('horses.archived_at')->exists()) {
            return ['state' => 'skipped', 'message' => 'Pas envoyé : aucun cheval actif sur ce compte.'];
        }

        return ['state' => 'late', 'message' => 'Prévu à '.$time.' mais pas encore parti : les tâches planifiées du serveur ne sont pas passées depuis.'.(self::cronAlive() ? '' : ' Le cron du serveur ne tourne pas (voir Administration › Notifications).')];
    }
}
