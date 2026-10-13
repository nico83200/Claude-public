<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use App\Services\WeeklyDigest;
use Illuminate\Console\Command;

/**
 * Récapitulatif de la semaine, le dimanche soir : notification sur le téléphone (résumé)
 * et e-mail (détail). Une fois par semaine au plus, rien si la semaine est vide.
 */
class SendWeeklyDigest extends Command
{
    protected $signature = 'reminders:weekly {--force : Envoyer même si ce n\'est pas dimanche}';

    protected $description = 'Envoie le récapitulatif de la semaine à venir';

    public function handle(WeeklyDigest $digests): int
    {
        if (! now()->isSunday() && ! $this->option('force')) {
            return self::SUCCESS;
        }
        $today = now()->toDateString();
        $sent = 0;
        User::query()->whereNull('deleted_at')->whereNull('suspended_at')
            ->where('last_seen_at', '>=', now()->subDays(60))
            ->whereDoesntHave('profile', fn ($q) => $q->where('weekly_digest', false)->orWhere('weekly_digest_sent_on', '>=', $today))
            ->with(['profile', 'memberships.role'])
            ->chunkById(100, function ($users) use ($digests, $today, &$sent) {
                foreach ($users as $user) {
                    $digest = $digests->for($user);
                    // Marqué même sans envoi : on ne recalcule pas toute la soirée.
                    UserProfile::updateOrCreate(['user_id' => $user->id], [])->forceFill(['weekly_digest_sent_on' => $today])->save();
                    if ($digests->isEmpty($digest)) {
                        continue;
                    }
                    $user->notify(new AppNotification('weekly_digest', 'Votre semaine avec vos chevaux', $digests->summary($digest),
                        route('digest.show'), mailBody: "Voici ce qui vous attend du ".$digest['from']->translatedFormat('l j F').' au '.$digest['to']->translatedFormat('l j F')." :\n\n".$digests->text($digest)));
                    $sent++;
                }
            });
        $this->info($sent.' récapitulatif(s) envoyé(s).');

        return self::SUCCESS;
    }
}
