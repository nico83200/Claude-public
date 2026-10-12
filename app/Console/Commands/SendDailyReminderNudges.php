<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;

/**
 * Suggère, de temps en temps, d'activer le rappel quotidien aux utilisateurs actifs qui ne l'ont pas
 * coché : au plus 3 fois, espacées de 2 semaines, jamais après « Ne plus me le proposer ».
 */
class SendDailyReminderNudges extends Command
{
    public const MAX = 3;

    public const EVERY_DAYS = 14;

    protected $signature = 'reminders:daily-nudge';

    protected $description = 'Propose d\'activer le rappel quotidien aux utilisateurs qui ne l\'ont pas coché';

    public function handle(): int
    {
        $sent = 0;
        User::query()
            ->whereNull('deleted_at')->whereNull('suspended_at')
            ->where('created_at', '<=', now()->subDays(3))
            ->where('last_seen_at', '>=', now()->subDays(30))
            // Sans profil enregistré = réglages par défaut, rappel quotidien non activé.
            ->where(fn ($u) => $u->doesntHave('profile')->orWhereHas('profile', fn ($q) => $q->whereNull('daily_reminder_at')->whereNull('daily_nudge_stopped_at')
                ->where('daily_nudge_count', '<', self::MAX)
                ->where(fn ($w) => $w->whereNull('daily_nudge_at')->orWhere('daily_nudge_at', '<=', now()->subDays(self::EVERY_DAYS)))))
            ->with('profile')->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $user->notify(new AppNotification('daily_nudge', 'Un petit rappel chaque soir ?',
                        'Activez le rappel quotidien : Jack&Cie vous prévient à l\'heure choisie, seulement si vous n\'avez rien noté dans la journée.',
                        route('settings.notifications', ['depuis' => 'rappel']).'#rappel-quotidien', mail: false));
                    $profile = $user->profile ?? UserProfile::create(['user_id' => $user->id]);
                    $profile->forceFill(['daily_nudge_count' => (int) $profile->daily_nudge_count + 1, 'daily_nudge_at' => now()])->save();
                    $sent++;
                }
            });
        $this->info($sent.' suggestion(s) envoyée(s).');

        return self::SUCCESS;
    }
}
